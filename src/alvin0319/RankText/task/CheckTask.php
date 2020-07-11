<?php
declare(strict_types=1);
namespace alvin0319\RankText\task;

use alvin0319\RankText\RankTextPlugin;
use pocketmine\scheduler\Task;
use pocketmine\Server;

class CheckTask extends Task{

	public function onRun(int $unused) : void{
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			foreach(RankTextPlugin::getInstance()->getTexts() as $text){
				if($player->distance($text->getPos()) <= $player->getViewDistance() && $player->getLevel()->getFolderName() === $text->getPos()->getLevel()->getFolderName()){
					$text->spawnTo($player);
				}else{
					$text->despawnTo($player);
				}
			}
		}
	}
}