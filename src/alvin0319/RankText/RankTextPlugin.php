<?php
declare(strict_types=1);
namespace alvin0319\RankText;

use alvin0319\RankText\command\RankTextCommand;
use alvin0319\RankText\task\CheckTask;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\level\Position;
use pocketmine\level\sound\ClickSound;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\plugin\PluginBase;
use function count;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function floatval;
use function implode;
use function json_decode;
use function json_encode;

class RankTextPlugin extends PluginBase implements Listener{

	/** @var RankTextPlugin|null */
	private static $instance = null;

	/** @var RankText[] */
	protected $texts = [];

	public function onLoad() : void{
		self::$instance = $this;
	}

	public static function getInstance() : RankTextPlugin{
		return self::$instance;
	}

	public function onEnable() : void{
		$data = json_decode(file_exists($file = $this->getDataFolder() . "Data.json") ? file_get_contents($file) : "{}");
		foreach($data as $pos){
			[$x, $y, $z, $world] = explode(":", $pos);
			$position = new Position(floatval($x), floatval($y), floatval($z), $this->getServer()->getLevelByName($world));
			$this->texts[] = new RankText($position);
		}
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->getScheduler()->scheduleRepeatingTask(new CheckTask(), 20);
		$this->getServer()->getCommandMap()->register("ranktext", new RankTextCommand());
	}

	public function onDisable() : void{
		if(count($this->texts) > 0){
			$data = [];
			foreach($this->texts as $text)
				$data[] = implode(":", [$text->getPos()->getX(), $text->getPos()->getY(), $text->getPos()->getZ(), $text->getPos()->getLevel()->getFolderName()]);
			file_put_contents($this->getDataFolder() . "Data.json", json_encode($data));
		}
	}

	/**
	 * @return RankText[]
	 */
	public function getTexts() : array{
		return $this->texts;
	}

	public function onPlayerQuit(PlayerQuitEvent $event) : void{
		$player = $event->getPlayer();
		foreach($this->getTexts() as $text)
			$text->remove($player);
	}

	public function spawnText(Position $pos) : void{
		$this->texts[] = new RankText($pos);
	}

	public function onDataPacketReceived(DataPacketReceiveEvent $event) : void{
		$packet = $event->getPacket();
		$player = $event->getPlayer();
		if($packet instanceof InventoryTransactionPacket){
			if($packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY){
				$entityId = $packet->trData->entityRuntimeId;
				foreach($this->getTexts() as $text){
					if($text->getId() === $entityId){
						switch($packet->trData->actionType){
							case InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_ATTACK:
								$text->setNowPageFor($player, $text->getNowPageFor($player) - 1);
								$player->getLevel()->addSound(new ClickSound($player));
								break;
							case InventoryTransactionPacket::USE_ITEM_ON_ENTITY_ACTION_INTERACT:
								$text->setNowPageFor($player, $text->getNowPageFor($player) + 1);
								$player->getLevel()->addSound(new ClickSound($player));
								break;
						}
					}
				}
			}
		}
	}
}