<?php
declare(strict_types=1);
namespace alvin0319\RankText;

use onebone\economyapi\EconomyAPI;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\ItemFactory;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\RemoveActorPacket;
use pocketmine\network\mcpe\protocol\SetActorDataPacket;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\network\mcpe\protocol\types\SkinAdapterSingleton;
use pocketmine\Player;
use pocketmine\utils\UUID;
use function array_slice;
use function array_values;
use function arsort;
use function ceil;
use function count;
use function str_repeat;
use function str_replace;

class RankText{

	/** @var int */
	protected $entityRuntimeId;

	/** @var UUID */
	protected $uuid;

	/** @var Player[] */
	protected $hasSpawned = [];

	/** @var Position */
	protected $pos;

	/** @var int[] */
	protected $rankPage = [];

	public function __construct(Position $pos){
		$this->pos = $pos;
		$this->entityRuntimeId = Entity::$entityCount++;
		$this->uuid = UUID::fromRandom();
	}

	public function getId() : int{
		return $this->entityRuntimeId;
	}

	public function setTextFor(Player $player) : void{
		if(!isset($this->hasSpawned[$player->getId()])){
			return;
		}
		$allMoney = EconomyAPI::getInstance()->getAllMoney();
		arsort($allMoney);
		$str = "§b§l[ §fMoney rank (§a" . ceil(count($allMoney) / 5) . " §fof §a" . $this->getNowPageFor($player) . "§f) §b]\n§r§f";
		$str = str_replace(["{MAX}", "{NOW}"], [ceil(count($allMoney) / 5), $this->getNowPageFor($player)], $str);

		$sliced = array_slice($allMoney, ($this->getNowPageFor($player) - 1) * 6, 5);
		$c = 1;
		foreach($sliced as $name => $value){
			$rank = ($this->getNowPageFor($player) - 1) * 5 + $c;
			$str .= "§b[{$rank}] §r§7{$name}: {$value}\n";
			$c++;
		}
		$str .= "§eLeft §aclick to move previous, §eRight §aclick to move next.";
		$pk = new SetActorDataPacket();
		$pk->entityRuntimeId = $this->entityRuntimeId;
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_IMMOBILE],
			Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01],
			Entity::DATA_NAMETAG => [Entity::DATA_TYPE_STRING, $str]
		];
		$player->sendDataPacket($pk);
	}

	public function spawnTo(Player $player) : void{
		if(isset($this->hasSpawned[$player->getName()])){
			$this->setTextFor($player);
			return;
		}
		$this->hasSpawned[$player->getId()] = $player;

		$pk = new AddPlayerPacket();
		$pk->entityRuntimeId = $this->entityRuntimeId;
		$pk->item = ItemFactory::get(0);
		$pk->position = $this->pos->floor();
		$pk->uuid = $this->uuid;
		$pk->username = "";
		$pk->metadata = [
			Entity::DATA_FLAGS => [Entity::DATA_TYPE_LONG, 1 << Entity::DATA_FLAG_IMMOBILE],
			Entity::DATA_SCALE => [Entity::DATA_TYPE_FLOAT, 0.01]
		];
		$player->sendDataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;
		$pk->entries = [PlayerListEntry::createAdditionEntry($this->uuid, $this->entityRuntimeId, "RankText #" . $this->entityRuntimeId, SkinAdapterSingleton::get()->toSkinData(new Skin("Standard_Custom", str_repeat("\x00", 8192))))];
		$player->sendDataPacket($pk);

		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_REMOVE;
		$pk->entries = [PlayerListEntry::createRemovalEntry($this->uuid)];
		$player->sendDataPacket($pk);
		$this->setTextFor($player);
	}

	public function despawnTo(Player $player) : void{
		if(!isset($this->hasSpawned[$player->getName()])){
			return;
		}
		unset($this->hasSpawned[$player->getId()]);

		$pk = new RemoveActorPacket();
		$pk->entityUniqueId = $this->entityRuntimeId;
		$player->sendDataPacket($pk);
	}

	/**
	 * @return Player[]
	 */
	public function getViewers() : array{
		return array_values($this->hasSpawned);
	}

	public function getNowPageFor(Player $player) : int{
		return $this->rankPage[$player->getName()] ?? 1;
	}

	public function setNowPageFor(Player $player, int $page) : void{
		if($page < 1)
			return;
		if($page > ceil(count(EconomyAPI::getInstance()->getAllMoney()) / 5))
			return;
		$this->rankPage[$player->getName()] = $page;
	}

	public function remove(Player $player) : void{
		$this->despawnTo($player);
		if(isset($this->rankPage[$player->getName()]))
			unset($this->rankPage[$player->getName()]);
	}

	public function getPos() : Position{
		return $this->pos;
	}
}