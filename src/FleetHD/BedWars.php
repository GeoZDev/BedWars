<?php 
namespace FleetHD;
use pocketmine\block\Block;
use pocketmine\Command\Command;
use pocketmine\Command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\entity\Villager;
use pocketmine\event\player\PlayerExperienceChangeEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\inventory\ChestInventory;
use pocketmine\item\Item;
use pocketmine\level\format\FullChunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\tile\Chest;
use pocketmine\tile\Sign;
use pocketmine\tile\Tile;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Bedwars extends PluginBase implements Listener {
    public $prefix = TextFormat::GRAY . "[" . TextFormat::RED . "Bedwars" . TextFormat::GRAY . "]" . TextFormat::WHITE . " ";
    public $registerSign = false;
    public $registerSignWHO = "";
    public $registerSignArena = "Arena1";
    public $registerBed = false;
    public $registerBedWHO = "";
    public $registerBedArena = "Arena1";
    public $registerBedTeam = "White";
    public $mode = 0;
    public $arena = "Arena1";
    public $lasthit = array();
    public $pickup = array();
    public $isShopping = array();
    public $breakableblocks = array();
    
	
	public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("Plugin was activated!! �bTraduction: GeoZDev!");
        @mkdir($this->getDataFolder());
        @mkdir($this->getDataFolder() . "Arenas");
        @mkdir($this->getDataFolder() . "Maps");
        $files = scandir($this->getDataFolder() . "Arenas");
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                $filename = str_replace(".yml", "", $filename);
                $this->resetArena($filename);
                $levels = $this->getArenaWorlds($filename);
                foreach ($levels as $levelname) {
                    $level = $this->getServer()->getLevelByName($levelname);
                    if ($level instanceof Level) {
                        $this->getServer()->unloadLevel($level);
                    }
                    $this->copymap($this->getDataFolder() . "Maps/" . $levelname, $this->getServer()->getDataPath() . "worlds/" . $levelname);
                    $this->getServer()->loadLevel($levelname);
                }
                $this->getServer()->loadLevel($this->getWarteLobby($filename));
            }
        }
        $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if (empty($cfg->get("LobbyTimer"))) {
            $cfg->set("LobbyTimer", 61);
            $cfg->save();
        }
        if (empty($cfg->get("GameTimer"))) {
            $cfg->set("GameTimer", 30 * 60 + 1);
            $cfg->save();
        }
        if (empty($cfg->get("EndTimer"))) {
            $cfg->set("EndTimer", 16);
            $cfg->save();
        }
        if (empty($cfg->get("BreakableBlocks"))) {
            $cfg->set("BreakableBlocks", array(Item::SANDSTONE, Item::CHEST));
            $cfg->save();
        }
        $this->breakableblocks = $cfg->get("BreakableBlocks");
        $shop = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
        if ($shop->get("Shop") == null) {
            $shop->set("Shop", array(Item::SANDSTONE, array(array(Item::SANDSTONE, 4, 336, 1), array(Item::GLASS, 1, 336, 5), array(Item::OBSIDIAN, 1, 264, 1)), Item::WOODEN_PICKAXE, array(array(Item::WOODEN_PICKAXE, 1, 336, 10), array(Item::STONE_PICKAXE, 1, 265, 5), array(Item::IRON_PICKAXE, 1, 266, 10), array(Item::DIAMOND_PICKAXE, 1, 264, 5)), Item::BOW, array(array(Item::BOW, 1, 264, 1), array(Item::ARROW, 2, 266, 1)), Item::WOODEN_SWORD, array(array(Item::WOODEN_SWORD, 1, 336, 20), array(Item::STONE_SWORD, 1, 265, 2), array(Item::IRON_SWORD, 1, 265, 10), array(Item::DIAMOND_SWORD, 1, 264, 5)), Item::IRON_CHESTPLATE, array(array(Item::LEATHER_CAP, 1, 336, 5), array(Item::LEATHER_PANTS, 1, 336, 10), array(Item::LEATHER_BOOTS, 1, 336, 5), array(Item::LEATHER_TUNIC, 1, 336, 10), array(Item::CHAIN_CHESTPLATE, 1, 265, 5), array(Item::IRON_CHESTPLATE, 1, 266, 2), array(Item::DIAMOND_CHESTPLATE, 1, 264, 5))));
            $shop->save();
        }
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWRefreshSigns($this), 20);
        $this->getServer()->getScheduler()->scheduleRepeatingTask(new BWGameSender($this), 20);
    }
    
	
	public function copymap($src, $dst) {
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }
    
	
	
	public function getTeams($arena) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $array = array();
        foreach ($this->getAllTeams() as $team) {
            if (!empty($config->getNested("Spawn." . $team))) {
                $array[] = $team;
            }
        }
        return $array;
    }
    
	
	
	public function getPlayers($arena) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $playersXXX = $config->get("Players");
        $players = array();
        foreach ($playersXXX as $x) {
            if ($x != "steve steve") {
                $players[] = $x;
            }
        }
        return $players;
    }
    
	
	public function getTeam($pn) {
        $pn = str_replace("�", "", $pn);
        $pn = str_replace(TextFormat::ESCAPE, "", $pn);
        $color = $pn{0};
        return $this->convertColorToTeam($color);
    }
    
	
	public function getAvailableTeams($arena) {
        $teams = $this->getTeams($arena);
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $players = $this->getPlayers($arena);
        $availableTeams = array();
        $ppt = (int)$config->get("PlayersPerTeam");
        $teamcount = 0;
        foreach ($teams as $team) {
            foreach ($players as $pn) {
                $p = $this->getServer()->getPlayerExact($pn);
                if ($p != null) {
                    $pnn = $p->getNameTag();
                    if ($this->getTeam($pnn) === $team) {
                        $teamcount++;
                    }
                }
            }
            if ($teamcount < $ppt) {
                $availableTeams[] = $team;
            }
            $teamcount = 0;
        }
        $array = array();
        $teamcount = 0;
        $teamcount2 = 0;
        foreach ($availableTeams as $team) {
            if (count($array) == 0) {
                $array[] = $team;
            } else {
                foreach ($players as $pn) {
                    $p = $this->getServer()->getPlayerExact($pn);
                    if ($p != null) {
                        $pnn = $p->getNameTag();
                        if ($this->getTeam($pnn) === $team) {
                            $teamcount++;
                        }
                    }
                }
                foreach ($players as $pn) {
                    $p = $this->getServer()->getPlayerExact($pn);
                    if ($p != null) {
                        $pnn = $p->getNameTag();
                        if ($this->getTeam($pnn) === $array[0]) {
                            $teamcount2++;
                        }
                    }
                }
                if ($teamcount >= $teamcount2) {
                    array_push($array, $team);
                } else {
                    array_unshift($array, $team);
                }
                $teamcount = 0;
                $teamcount2 = 0;
            }
        }
        return $array;
    }
    
	
	
	public function getAvailableTeam($arena) {
        $teams = $this->getAvailableTeams($arena);
        if (isset($teams[0])) {
            return $teams[0];
        } else {
            return "Beyaz";
        }
    }
    
	
	
	public function getAliveTeams($arena) {
        $alive = array();
        $teams = $this->getTeams($arena);
        $players = $this->getPlayers($arena);
        $teamcount = 0;
        foreach ($teams as $team) {
            foreach ($players as $pn) {
                $p = $this->getServer()->getPlayerExact($pn);
                if ($p != null) {
                    $pnn = $p->getNameTag();
                    if ($this->getTeam($pnn) == $team) {
                        $teamcount++;
                    }
                }
            }
            if ($teamcount != 0) {
                $alive[] = $team;
            }
            $teamcount = 0;
        }
        return $alive;
    }
    
	
	
	public function convertColorToTeam($color) {
        if ($color == "9") return "Blue";
        if ($color == "c") return "Red";
        if ($color == "a") return "Green";
        if ($color == "e") return "Yellow";
        if ($color == "5") return "Violet";
        if ($color == "0") return "Black";
        if ($color == "7") return "Gray";
        if ($color == "b") return "LightBlue";
        return "White";
    }
    
	
	
	public function convertTeamToColor($team) {
        if ($team == "Mavi") return "9";
        if ($team == "K?rm?z?") return "c";
        if ($team == "Ye?il") return "a";
        if ($team == "Sar?") return "e";
        if ($team == "Mor") return "5";
        if ($team == "Siyah") return "0";
        if ($team == "Gri") return "7";
        if ($team == "Turkuaz") return "b";
        return "f";
    }
    
	
	
	public function getTeamColor($team) {
        if ($team == "Mavi") return TextFormat::BLUE;
        if ($team == "K?rm?z?") return TextFormat::RED;
        if ($team == "Ye?il") return TextFormat::GREEN;
        if ($team == "Sar?") return TextFormat::YELLOW;
        if ($team == "Mor") return TextFormat::DARK_PURPLE;
        if ($team == "Siyah") return TextFormat::BLACK;
        if ($team == "Gri") return TextFormat::GRAY;
        if ($team == "Turkuaz") return TextFormat::AQUA;
        return TextFormat::Beyaz;
    }
    
	
	
	public function resetArena($arena, $mapreset = false) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        if ($mapreset === true) {
            $this->resetMaps($arena);
        }
        $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
        $config->set("GameTimer", $cfg->get("GameTimer"));
        $config->set("EndTimer", $cfg->get("EndTimer"));
        $config->set("Status", "Lobby");
        $config->set("Players", array("steve steve"));
        $config->save();
        foreach ($this->getTeams($arena) as $team) {
            $config->setNested("Bed." . $team . ".Alive", true);
            $config->save();
        }
        $this->getLogger()->info(TextFormat::GREEN . "Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . "Installed!");
    }
    
	
	
	public function createArena($arena, $teams, $ppt) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $cfg = new Config($this->getDataFolder() . "config.yml", Config::YAML);
        $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
        $config->set("GameTimer", $cfg->get("GameTimer"));
        $config->set("EndTimer", $cfg->get("EndTimer"));
        $config->set("Status", "Lobby");
        $config->set("Players", array("steve steve"));
        $config->set("Teams", $teams);
        $config->set("PlayersPerTeam", $ppt);
        $config->save();
        $this->getLogger()->info(TextFormat::GREEN . "Arena " . TextFormat::AQUA . $arena . TextFormat::GREEN . " Installed!");
    }
    
	
	
	public function resetMaps($arena) {
        $levels = $this->getArenaWorlds($arena);
        foreach ($levels as $levelname) {
            $level = $this->getServer()->getLevelByName($levelname);
            if ($level instanceof Level) {
                $this->getServer()->unloadLevel($level);
            }
            $this->copymap($this->getDataFolder() . "Maps/" . $levelname, $this->getServer()->getDataPath() . "worlds/" . $levelname);
            $this->getServer()->loadLevel($levelname);
        }
    }
    
	
	
	public function saveMaps($arena) {
        $levels = $this->getArenaWorlds($arena);
        foreach ($levels as $levelname) {
            $level = $this->getServer()->getLevelByName($levelname);
            $this->copymap($this->getServer()->getDataPath() . "worlds/" . $levelname, $this->getDataFolder() . "Maps/" . $levelname);
        }
    }
    
	
	
	public function getFigthWorld($arena) {
        $level = "noWorld";
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        foreach ($this->getTeams($arena) as $team) {
            $level = $config->getNested("Spawn." . $team . ".Welt");
        }
        return $level;
    }
    
	
	public function getWarteLobby($arena) {
        $levels = array();
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        return $config->getNested("Spawn.Lobby.Welt");
    }
    
	
	
	public function getArenaWorlds($arena) {
        $levels = array();
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        foreach ($this->getAllTeams() as $team) {
            if (!empty($config->getNested("Spawn." . $team . ".Welt"))) {
                $newlevel = $config->getNested("Spawn." . $team . ".Welt");
                if (!in_array($newlevel, $levels)) {
                    $levels[] = $newlevel;
                }
            }
        }
        return $levels;
    }
    
	
	
	public function setSpawn($arena, $team, Player $p) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $config->setNested("Spawn." . $team . ".Welt", $p->getLevel()->getName());
        $config->setNested("Spawn." . $team . ".X", $p->getX());
        $config->setNested("Spawn." . $team . ".Y", $p->getY());
        $config->setNested("Spawn." . $team . ".Z", $p->getZ());
        $config->setNested("Spawn." . $team . ".Yaw", $p->getYaw());
        $config->setNested("Spawn." . $team . ".Pitch", $p->getPitch());
        $config->save();
    }
    
	
	
	public function setLobby($arena, Player $p) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $config->setNested("Spawn.Lobby.Welt", $p->getLevel()->getName());
        $config->setNested("Spawn.Lobby.X", $p->getX());
        $config->setNested("Spawn.Lobby.Y", $p->getY());
        $config->setNested("Spawn.Lobby.Z", $p->getZ());
        $config->setNested("Spawn.Lobby.Yaw", $p->getYaw());
        $config->setNested("Spawn.Lobby.Pitch", $p->getPitch());
        $config->save();
    }
    
	
	
	public function arenaExists($arena) {
        $files = scandir($this->getDataFolder() . "Arenas");
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                $filename = str_replace(".yml", "", $filename);
                if ($filename == $arena) {
                    return true;
                }
            }
        }
        return false;
    }
    
	
	
	public function TeleportToWaitingLobby($arena, Player $p) {
        $p->setHealth(20);
        $p->setFood(20);
        $p->setGamemode(0);
        $p->getInventory()->clearAll();
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $welt = $config->getNested("Spawn.Lobby.Welt");
        $x = $config->getNested("Spawn.Lobby.X");
        $y = $config->getNested("Spawn.Lobby.Y");
        $z = $config->getNested("Spawn.Lobby.Z");
        $yaw = $config->getNested("Spawn.Lobby.Yaw");
        $pitch = $config->getNested("Spawn.Lobby.Pitch");
        $p->teleport($this->getServer()->getLevelByName($welt)->getSafeSpawn(), 0, 0);
        $p->teleport(new Vector3($x, $y, $z), $yaw, $pitch);
    }
    
	
	
	public function getAllTeams() {
        $teams = array("Blue", "Red", "Green", "Yellow", "Purple", "Black", "Gray", "Aqua");
        return $teams;
    }
    
	
	
	public function Debug($debug) {
        $this->getLogger()->info($debug);
    }
    
	
	
	public function addPlayerToArena($arena, $name) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $players = $this->getPlayers($arena);
        $players[] = $name;
        $config->set("Players", $players);
        $config->save();
    }
    
	
	
	public function removePlayerFromArena($arena, $name) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $playersXXX = $this->getPlayers($arena);
        $players = array();
        foreach ($playersXXX as $pn) {
            if ($pn != $name) {
                $players[] = $pn;
            }
        }
        $config->set("Players", $players);
        $config->save();
    }
    
	
	
	public function getArena(Player $p) {
        $files = scandir($this->getDataFolder() . "Arenas");
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                $arena = str_replace(".yml", "", $filename);
                $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                if (in_array($p->getName(), $config->get("Players"))) {
                    return $arena;
                }
            }
        }
        return "-";
    }
    
	
	
	public function inArena(Player $p) {
        $files = scandir($this->getDataFolder() . "Arenas");
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                $arena = str_replace(".yml", "", $filename);
                $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                if (in_array($p->getName(), $config->get("Players"))) {
                    return true;
                }
            }
        }
        return false;
    }
    
	
	
	public function TeleportToTeamSpawn(Player $p, $team, $arena) {
        $p->setHealth(20);
        $p->setFood(20);
        $p->setGamemode(0);
        $p->getInventory()->clearAll();
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $welt = $config->getNested("Spawn." . $team . ".Welt");
        $x = $config->getNested("Spawn." . $team . ".X");
        $y = $config->getNested("Spawn." . $team . ".Y");
        $z = $config->getNested("Spawn." . $team . ".Z");
        $yaw = $config->getNested("Spawn." . $team . ".Yaw");
        $pitch = $config->getNested("Spawn." . $team . ".Pitch");
        if ($p->getLevel() != $this->getServer()->getLevelByName($welt)) {
            $p->teleport($this->getServer()->getLevelByName($welt)->getSafeSpawn(), 0, 0);
        }
        $p->teleport(new Vector3($x, $y, $z), $yaw, $pitch);
    }
    
	
	
	public function getTeamByBlockDamage($damage) {
        if ($damage == 10) {
            return "Mor";
        }
        if ($damage == 9) {
            return "Turkuaz";
        }
        if ($damage == 4) {
            return "Sar?";
        }
        if ($damage == 5) {
            return "Ye?il";
        }
        if ($damage == 11) {
            return "Mavi";
        }
        if ($damage == 14) {
            return "K?rm?z?";
        }
        if ($damage == 15) {
            return "Siyah";
        }
        if ($damage == 7) {
            return "Gri";
        }
        return "Beyaz";
    }
    
	
	
	public function openShop(Player $o) {
        $o->getLevel()->setBlock(new Vector3($o->getFloorX(), $o->getFloorY() - 4, $o->getFloorZ()), Block::get(Block::CHEST));
        $nbt = new CompoundTag("", [new ListTag("Items", []), new StringTag("id", Tile::CHEST), new IntTag("x", $o->getFloorX()), new IntTag("y", $o->getFloorY() - 4), new IntTag("z", $o->getFloorZ()), new StringTag("CustomName", "�6EGGWars �fMarket") ]);
        $nbt->Items->setTagType(NBT::TAG_Compound);
        $tile = Tile::createTile("Chest", $o->getLevel(), $nbt);
        if ($tile instanceof Chest) {
            $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
            $shop = $config->get("Shop");
            $tile->setName("�aMarket");
            $tile->getInventory()->clearAll();
            for ($i = 0;$i < count($shop);$i+= 2) {
                $slot = $i / 2;
                $tile->getInventory()->setItem($slot, Item::get($shop[$i], 0, 1));
            }
            $tile->getInventory()->setItem($tile->getInventory()->getSize() - 1, Item::get(Item::WOOL, 14, 1));
            $o->addWindow($tile->getInventory());
        }
    }
    
	
	
	
	public function createVillager($x, $y, $z, Level $level) {
        $x+= 0.5;
        $z+= 0.5;
        $nbt = new CompoundTag;
        $nbt->Pos = new ListTag("Pos", [new DoubleTag("", $x), new DoubleTag("", $y), new DoubleTag("", $z) ]);
        $nbt->Rotation = new ListTag("Rotation", [new FloatTag("", 0), new FloatTag("", 0) ]);
        $nbt->Health = new ShortTag("Health", 10);
        $nbt->CustomName = new StringTag("CustomName", TextFormat::GOLD . "SHOP");
        $nbt->CustomNameVisible = new ByteTag("CustomNameVisible", 1);
        $level->loadChunk($x >> 4, $z >> 4);
        $villager = Entity::createEntity("Villager", $level->getChunk($x >> 4, $y >> 4), $nbt);
        $villager->spawnToAll();
    }
    
	
	
	
	public function getWoolDamageByTeam($team) {
        if ($team == "Mavi") {
            return 11;
        }
        if ($team == "K?rm?z?") {
            return 14;
        }
        if ($team == "Ye?il") {
            return 5;
        }
        if ($team == "Sar?") {
            return 4;
        }
        if ($team == "Turkuaz") {
            return 9;
        }
        if ($team == "Siyah") {
            return 15;
        }
        if ($team == "Mor") {
            return 10;
        }
        if ($team == "Gri") {
            return 7;
        }
        return 0;
    }
    
	
	
	public function setTeamSelectionItems(Player $player, $arena) {
        $player->getInventory()->clearAll();
        $player->setNameTag($player->getName());
        $teams = $this->getTeams($arena);
        foreach ($teams as $team) {
            $teamwool = $this->getWoolDamageByTeam($team);
            $player->getInventory()->addItem(Item::get(Item::WOOL, $teamwool, 1));
        }
    }
    
	
	
	public function getArenaStatus($arena) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $status = $config->get("Status");
        return $status;
    }
    
	
	
	public function sendIngameScoreboard(Player $p, $arena) {
        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
        $popup = TextFormat::GRAY . " [" . TextFormat::GOLD . "BedWars" . TextFormat::GRAY . "]\n";
        $teams = $this->getTeams($arena);
        $teamscount = 0;
        if (count($teams) >= 4) {
            foreach ($teams as $team) {
                if ($teamscount == 4) {
                    $popup = $popup . "\n";
                }
                if (in_array($team, $this->getAliveTeams($arena))) {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::GREEN . "+" . TextFormat::GRAY . "]";
                } else {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::RED . "-" . TextFormat::GRAY . "]";
                }
                $teamscount++;
            }
        } else {
            foreach ($teams as $team) {
                if (in_array($team, $this->getAliveTeams($arena))) {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::GREEN . "x" . TextFormat::GRAY . "]";
                } else {
                    $popup = $popup . " " . $this->getTeamColor($team) . $team . TextFormat::GRAY . " [" . TextFormat::RED . "x" . TextFormat::GRAY . "]";
                }
            }
        }
        $p->sendPopup($popup);
    }
    
	
	
	public function onTransaction(InventoryTransactionEvent $event) {
        $trans = $event->getTransaction()->getTransactions();
        $inv = $event->getTransaction()->getInventories();
        $player = null;
        $chestBlock = null;
        foreach ($trans as $t) {
            foreach ($inv as $inventory) {
                $chest = $inventory->getHolder();
                if ($chest instanceof Chest) {
                    $chestBlock = $chest->getBlock();
                    $transaction = $t;
                }
                if ($chest instanceof Player) {
                    $player = $chest;
                }
            }
        }
        if ($player != null && $chestBlock != null && isset($transaction)) {
            if ($this->inArena($player)) {
                $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
                $all = $config->get("Shop");
                $arena = $this->getArena($player);
                $chestTile = $player->getLevel()->getTile($chestBlock);
                if ($chestTile instanceof Chest) {
                    $TargetItemID = $transaction->getTargetItem()->getId();
                    $TargetItemDamage = $transaction->getTargetItem()->getDamage();
                    $TargetItem = $transaction->getTargetItem();
                    $inventoryTrans = $chestTile->getInventory();
                    if ($this->isShopping[$player->getName() ] != "ja") {
                        $zahl = 0;
                        for ($i = 0;$i < count($all);$i+= 2) {
                            if ($TargetItemID == $all[$i]) {
                                $zahl++;
                            }
                        }
                        if ($zahl == count($all)) {
                            $this->isShopping[$player->getName() ] = "ja";
                        }
                    }
                    if ($this->isShopping[$player->getName() ] != "ja") {
                        $is = $inventoryTrans->getItem(1)->getId();
                        if ($is == 384) {
                            $this->isShopping[$player->getName() ] = "ja";
                        }
                    }
                    if ($this->isShopping[$player->getName() ] == "ja") {
                        if ($TargetItemID == Item::WOOL && $TargetItemDamage == 14) {
                            $event->setCancelled(true);
                            $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
                            $all = $config->get("Shop");
                            $chestTile->getInventory()->clearAll();
                            for ($i = 0;$i < count($all);$i = $i + 2) {
                                $slot = $i / 2;
                                $chestTile->getInventory()->setItem($slot, Item::get($all[$i], 0, 1));
                            }
                        }
                        $TransactionSlot = 0;
                        for ($i = 0;$i < $inventoryTrans->getSize();$i++) {
                            if ($inventoryTrans->getItem($i)->getId() == $TargetItemID) {
                                $TransactionSlot = $i;
                                break;
                            }
                        }
                        $is = $inventoryTrans->getItem(1)->getId();
                        if ($TransactionSlot % 2 != 0 && ($is == 264 or $is == 265 or $is == 266 or $is == 336)) {
                            $event->setCancelled(true);
                        }
                        if ($TargetItemID == 267 or $TargetItemID == 266 or $TargetItemID == 265 or $TargetItemID == 336) {
                            $event->setCancelled(true);
                        }
                        if ($TransactionSlot % 2 == 0 && ($is == 264 or $is == 265 or $is == 266 or $is == 336)) {
                            $Kosten = $inventoryTrans->getItem($TransactionSlot + 1)->getCount();
                            $player->getInventory()->addItem(Item::get($inventoryTrans->getItem($TransactionSlot)->getId(), $inventoryTrans->getItem($TransactionSlot)->getDamage(), $inventoryTrans->getItem($TransactionSlot)->getCount()));
                        }
                        $event->setCancelled(true);
                    }
                    if ($is != 264 or $is != 265 or $is != 266 or $is != 336) {
                        $event->setCancelled(true);
                        $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
                        $all = $config->get("Shop");
                        for ($i = 0;$i < count($all);$i+= 2) {
                            if ($TargetItemID == $all[$i]) {
                                $chestTile->getInventory()->clearAll();
                                $suball = $all[$i + 1];
                                $slot = 0;
                                for ($j = 0;$j < count($suball);$j++) {
                                    $chestTile->getInventory()->setItem($slot, Item::get($suball[$j][0], 0, $suball[$j][1]));
                                    $slot++;
                                    $chestTile->getInventory()->setItem($slot, Item::get($suball[$j][2], 0, $suball[$j][3]));
                                    $slot++;
                                }
                                break;
                            }
                        }
                        $chestTile->getInventory()->setItem($chestTile->getInventory()->getSize() - 1, Item::get(Item::WOOL, 14, 1));
                    }
                }
            }
        }
    }
    
	
	
	public function onItemDrop(PlayerDropItemEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $item = $event->getItem();
        if ($item->getId() == Item::WOOL) {
            if ($this->inArena($player)) {
                $arena = $this->getArena($player);
                $team = $this->getTeamByBlockDamage($item->getDamage());
                $event->setCancelled();
                if ($this->getArenaStatus($arena) == "Lobby") {
                    if ($team != $this->getTeam($player->getNameTag())) {
                        if (in_array($team, $this->getAvailableTeams($arena))) {
                            $player->setNameTag($this->getTeamColor($team) . $name);
                            $player->sendTitle(("�e$team"), ("�atak?ma kat?ld?n!"));
                            $player->getInventory()->removeItem($item);
                            $player->getInventory()->addItem($item);
                        } else {
                            $player->sendTitle(("�e$team"), ("�ctak?m dolu!"));
                            $player->getInventory()->removeItem($item);
                            $player->getInventory()->addItem($item);
                        }
                    } else {
                        $player->sendTitle(("�cZaten"), ("�e$team �ctak?mdas?n!"));
                        $player->getInventory()->removeItem($item);
                        $player->getInventory()->addItem($item);
                    }
                }
            }
        }
    }
    
	
	
	
	public function onChat(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if ($this->inArena($player)) {
            $arena = $this->getArena($player);
            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
            $team = $this->getTeam($player->getNameTag());
            $players = $this->getPlayers($arena);
            $status = $config->get("Status");
            $msg = $event->getMessage();
            $words = explode(" ", $msg);
            if ($status == "Lobby") {
                $event->setCancelled();
                foreach ($players as $pn) {
                    $p = $this->getServer()->getPlayerExact($pn);
                    if ($p != null) {
                        $p->sendMessage($name . "� " . $msg);
                    }
                }
            } else {
                if ($words[0] === "@" or $words[0] === "@herkes") {
                    array_shift($words);
                    $msg = implode(" ", $words);
                    $event->setCancelled();
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if ($p != null) {
                            $p->sendMessage(TextFormat::GRAY . "[" . TextFormat::RED . "Herkes" . TextFormat::GRAY . "] " . $player->getNameTag() . TextFormat::GRAY . "� " . TextFormat::WHITE . $msg);
                        }
                    }
                } else {
                    $event->setCancelled();
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if ($p != null) {
                            if ($this->getTeam($p->getNameTag()) == $this->getTeam($player->getNameTag())) {
                                $p->sendMessage(TextFormat::GRAY . "[" . $this->getTeamColor($this->getTeam($player->getNameTag())) . "Tak?m" . TextFormat::GRAY . "] " . $player->getNameTag() . TextFormat::GRAY . "� " . TextFormat::WHITE . $msg);
                            }
                        }
                    }
                }
            }
        }
    }
    
	
	
	
	public function onInvClose(InventoryCloseEvent $event) {
        $inventory = $event->getInventory();
        if ($inventory instanceof ChestInventory) {
            $config = new Config($this->getDataFolder() . "shop.yml", Config::YAML);
            $all = $config->get("Shop");
            $realChest = $inventory->getHolder();
            $first = $all[0];
            $second = $all[2];
            if (($inventory->getItem(0)->getId() == $first && $inventory->getItem(1)->getId() == $second) || $inventory->getItem(1)->getId() == 384) {
                $event->getPlayer()->getLevel()->setBlock(new Vector3($realChest->getX(), $realChest->getY(), $realChest->getZ()), Block::get(Block::AIR));
                $this->isShopping[$event->getPlayer()->getName() ] = "nein";
            }
        }
    }
    
	
	
	public function onJoin(PlayerJoinEvent $event) {
        $player = $event->getPlayer();
        $this->lasthit[$player->getName() ] = "no";
        $this->isShopping[$player->getName() ] = "nein";
        $player->setNameTag($player->getName());
    }
    
	
	
	public function onRespawn(PlayerRespawnEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        if ($this->inArena($player)) {
            $arena = $this->getArena($player);
            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
            $team = $this->getTeam($player->getNameTag());
            if ($config->getNested("Bed." . $team . ".Alive") == true) {
                $welt = $config->getNested("Spawn." . $team . ".Welt");
                $x = $config->getNested("Spawn." . $team . ".X");
                $y = $config->getNested("Spawn." . $team . ".Y");
                $z = $config->getNested("Spawn." . $team . ".Z");
                $level = $this->getServer()->getLevelByName($welt);
                $event->setRespawnPosition(new Position($x, $y, $z, $level));
            } else {
                $event->setRespawnPosition($this->getServer()->getDefaultLevel()->getSafeSpawn());
                $player->sendTitle(("�cYata??n"), ("�cyok edildi, art?k do?amazs?n!"));
                $this->removePlayerFromArena($arena, $name);
                $this->lasthit[$player->getName() ] = "no";
                $player->setNameTag($player->getName());
            }
        }
    }
    
	
	
	
	public function onDeath(PlayerDeathEvent $event) {
        $player = $event->getEntity();
        if ($player instanceof Player) {
            if ($this->inArena($player)) {
                $event->setDeathMessage("");
                $arena = $this->getArena($player);
                $cause = $player->getLastDamageCause();
                $players = $this->getPlayers($arena);
                if ($cause instanceof EntityDamageByEntityEvent) {
                    $killer = $cause->getDamager();
                    $event->setDrops(array());
                    if ($killer instanceof Player) {
                        foreach ($players as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                $p->sendMessage($this->prefix . TextFormat::YELLOW . $player->getNameTag() . TextFormat::RED . " adl? oyuncu " . TextFormat::YELLOW . $killer->getNameTag() . TextFormat::RED . " taraf?ndan �ld�r�ld�!");
                            }
                        }
                    } else {
                        foreach ($players as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                $p->sendMessage($this->prefix . TextFormat::YELLOW . $player->getNameTag() . TextFormat::RED . " �ld�!");
                            }
                        }
                    }
                } else {
                    $event->setDrops(array());
                    foreach ($players as $pn) {
                        $p = $this->getServer()->getPlayerExact($pn);
                        if ($p != null) {
                            if ($this->lasthit[$player->getName() ] != "no") {
                                $p2 = $this->getServer()->getPlayerExact($this->lasthit[$player->getName() ]);
                                if ($p2 != null) {
                                    $p->sendMessage($this->prefix . TextFormat::YELLOW . $player->getNameTag() . TextFormat::RED . " adl? oyuncu " . TextFormat::YELLOW . $p2->getNameTag() . TextFormat::RED . "taraf?ndan �ld�r�ld�!");
                                    $this->lasthit[$player->getName() ] = "no";
                                } else {
                                    $p->sendMessage($this->prefix . TextFormat::YELLOW . $player->getNameTag() . TextFormat::RED . " �ld�!");
                                }
                            } else {
                                $p->sendMessage($this->prefix . TextFormat::YELLOW . $player->getNameTag() . TextFormat::RED . " �ld�!");
                            }
                        }
                    }
                }
            }
        }
    }
    
	
	
	
	public function onHit(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if (!$player instanceof Player) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    if ($this->inArena($damager)) {
                        $event->setCancelled();
                        $this->isShopping[$damager->getName() ] = "ja";
                        $this->openShop($damager);
                    }
                }
            }
        } else {
            if ($this->inArena($player)) {
                $arena = $this->getArena($player);
                $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                if ($config->get("Status") == "Lobby") {
                    $event->setCancelled();
                }
            }
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if ($damager instanceof Player) {
                    if ($this->inArena($player)) {
                        $arena = $this->getArena($player);
                        $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                        if ($config->get("Status") == "Lobby") {
                            $event->setCancelled();
                        } else {
                            if ($this->getTeam($damager->getNameTag()) == $this->getTeam($player->getNameTag())) {
                                $event->setCancelled();
                                $damager->sendTitle(("�cTak?m"), ("�carkada??na zarar veremezsin!"));
                            } else {
                                $this->lasthit[$player->getName() ] = $damager->getName();
                            }
                        }
                    }
                }
            }
        }
    }
    
	
	
	public function onMove(PlayerMoveEvent $event) {
        $player = $event->getPlayer();
        if ($this->inArena($player)) {
            $arena = $this->getArena($player);
            $cause = $player->getLastDamageCause();
            $players = $this->getPlayers($arena);
            if ($player->getY() <= 4) {
                $player->setHealth(0);
            }
        }
    }
    
	
	
	
	public function onPlace(BlockPlaceEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        if ($this->inArena($player)) {
            $arena = $this->getArena($player);
            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
            if ($config->get("Status") == "Lobby") {
                $event->setCancelled();
                if ($block->getId() == Block::WOOL) {
                    $item = Item::get($block->getId(), $block->getDamage(), 1);
                    $arena = $this->getArena($player);
                    $team = $this->getTeamByBlockDamage($block->getDamage());
                    $event->setCancelled();
                    if ($team != $this->getTeam($player->getNameTag())) {
                        if (in_array($team, $this->getAvailableTeams($arena))) {
                            $player->setNameTag($this->getTeamColor($team) . $name);
                            $player->sendTitle(("�e$team"), ("�atak?ma kat?ld?n!"));
                            $player->getInventory()->removeItem($item);
                            $player->getInventory()->addItem($item);
                        } else {
                            $player->sendTitle(("�e$team"), ("�ctak?m dolu!"));
                            $player->getInventory()->removeItem($item);
                            $player->getInventory()->addItem($item);
                        }
                    } else {
                        $player->sendTitle(("�cZaten"), ("�e$team �ctak?mdas?n!"));
                        $player->getInventory()->removeItem($item);
                        $player->getInventory()->addItem($item);
                    }
                }
            } else {
                if (!in_array($block->getId(), $this->breakableblocks)) {
                    $event->setCancelled();
                }
            }
        }
    }
    
	
	
	
	public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $block2 = $player->getLevel()->getBlock(new Vector3($block->getX(), $block->getY() - 1, $block->getZ()), false);
        if ($this->inArena($player)) {
            $arena = $this->getArena($player);
            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
            $team = $this->getTeamByBlockDamage($block2->getDamage());
            if ($config->get("Status") != "Lobby") {
                if ($block->getId() == Block::BED_BLOCK) {
                    if ($team != $this->getTeam($player->getNameTag())) {
                        $config->setNested("Bed." . $team . ".Alive", false);
                        $config->save();
                        $event->setDrops(array());
                        $player->sendTitle(("�e$team"), ("�atak?m?n yata??n? y?kt?n!"));
                        foreach ($this->getPlayers($arena) as $pn) {
                            $p = $this->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                if ($team == $this->getTeam($p->getNameTag())) {
                                    $p->sendTitle(("�cTak?m?n?n"), ("�cyata?? y?k?ld?!"));
                                } else {
                                    $p->sendTitle(("�e$team"), ("�ctak?m?n yata?? y?k?ld?!"));
                                }
                            }
                        }
                    } else {
                        $player->sendTitle(("�cTak?m?n?n"), ("�cyata??n? y?kamazs?n!"));
                        $event->setCancelled();
                    }
                } elseif (!in_array($block->getId(), $this->breakableblocks)) {
                    $event->setCancelled();
                }
            } else {
                $event->setCancelled();
            }
        }
    }
    
	
	
	public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        $name = $player->getName();
        $block = $event->getBlock();
        $tile = $player->getLevel()->getTile($block);
        if ($this->registerBed == true && $this->registerBedWHO == $name) {
            $arena = $this->registerBedArena;
            $team = $this->registerBedTeam;
            $this->registerBed = false;
            $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
            $config->setNested("Bed." . $team . ".Welt", $block->getLevel()->getName());
            $config->setNested("Bed." . $team . ".X", $block->getX());
            $config->setNested("Bed." . $team . ".Y", $block->getY());
            $config->setNested("Bed." . $team . ".Z", $block->getZ());
            $config->setNested("Bed." . $team . ".Alive", true);
            $config->save();
            $player->sendMessage(TextFormat::GREEN . "Ba?ar?yla " . TextFormat::AQUA . $team . TextFormat::GREEN . " tak?m? " . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenaya ekledin!");
            $player->sendMessage(TextFormat::GREEN . "Kurulum -> /bw yardim");
        }
        if ($tile instanceof Sign) {
            $text = $tile->getText();
            if ($this->registerSign == true && $this->registerSignWHO == $name) {
                $arena = $this->registerSignArena;
                $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                $teams = (int)$config->get("Teams");
                $ppt = (int)$config->get("PlayersPerTeam");
                $maxplayers = $teams * $ppt;
                $tile->setText($this->prefix, $arena . " " . $teams . "x" . $ppt, TextFormat::GREEN . "Y�kleniyor!", TextFormat::GREEN . "0" . TextFormat::GRAY . "/" . TextFormat::RED . ".$maxplayers");
                $this->registerSign = false;
                $player->sendMessage(TextFormat::GREEN . "Ba?ar?yla " . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenaya kalkan eklendi!");
                $player->sendMessage(TextFormat::GREEN . "Kurulum -> /bw yardim");
            } elseif ($text[0] == $this->prefix) {
                if ($text[2] == TextFormat::GREEN . "Kat?l!") {
                    $arena = substr($text[1], 0, -4);
                    $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                    $status = $config->get("Status");
                    $maxplayers = $config->get("PlayersPerTeam") * $config->get("Teams");
                    $players = count($config->get("Players"));
                    if ($status == "Lobby") {
                        if ($players < $maxplayers) {
                            $this->TeleportToWaitingLobby($arena, $player);
                            $this->setTeamSelectionItems($player, $arena);
                            $this->addPlayerToArena($arena, $name);
                        } else {
                            $player->sendTitle(("�cArenaya"), ("�ckat?lamazs?n!"));
                        }
                    } else {
                        $player->sendTitle(("�cArenaya"), ("�ckat?lamazs?n!"));
                    }
                } else {
                    $player->sendTitle(("�cArenaya"), ("�ckat?lamazs?n!"));
                }
            }
        }
    }
    
	
	
	public function onCommand(CommandSender $sender, Command $cmd, string $label, array $args) : bool {
        $name = $sender->getName();
        if ($cmd->getName() == "Baslat" && $sender->hasPermission("bw.forcestart")) {
            if ($sender instanceof Player) {
                if ($this->inArena($sender)) {
                    $arena = $this->getArena($sender);
                    $config = new Config($this->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                    $config->set("LobbyTimer", 5);
                    $config->save();
                } else {
                    $sender->sendTitle(("�cArenada"), ("�cde?ilsin!"));
                }
            }
        }
        if ($cmd->getName() == "Bedwars" && $sender->isOP()) {
            if (!empty($args[0])) {
                if (strtolower($args[0]) == "yardim" && $sender->isOP()) {
                    $sender->sendMessage("�7=====�bFLEETHD�7==========");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw yardim " . TextFormat::GRAY . "[" . TextFormat::RED . "BedWars komutlar?n? g�sterir!" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw tabelaekle <arena> " . TextFormat::GRAY . "[" . TextFormat::RED . "Arena tabelas? ekler!" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw haritakaydet <arena> " . TextFormat::GRAY . "[" . TextFormat::RED . "Arenan?n bulundu?u d�nyay? kaydeder!" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw arenaekle <arena> <tak?msay?s?> <tak?mba??naoyuncusay?s?> " . TextFormat::GRAY . "[" . TextFormat::RED . "Add a new arena" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw lobiekle <arena>" . TextFormat::GRAY . "[" . TextFormat::RED . "Arenaya lobi ekler!" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw takimekle <arena> <tak?m>" . TextFormat::GRAY . "[" . TextFormat::RED . "Arenaya tak?m ekler!" . TextFormat::GRAY . "]");
                    $sender->sendMessage(TextFormat::GRAY . "-> " . TextFormat::AQUA . "/bw yatakekle <arena> <tak?m>" . TextFormat::GRAY . "[" . TextFormat::RED . "Arenaya tak?mlar?n yataklar?n? ekler!" . TextFormat::GRAY . "]");
                    $sender->sendMessage("�7-> �cBu eklenti FleetHD taraf?ndan fixlenerek d�zenlenmi?tir!");
                    $sender->sendMessage("�7-> �chttps://www.youtube.com/c/FleetHD kanal?na abone olman?z rica edilir!");
                    $sender->sendMessage("�7=====�bFLEETHD�7==========");
                } elseif (strtolower($args[0]) == "tabelaekle" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        $arena = $args[1];
                        if ($this->arenaExists($arena)) {
                            $this->registerSign = true;
                            $this->registerSignWHO = $name;
                            $this->registerSignArena = $arena;
                            $sender->sendMessage(TextFormat::GREEN . "Bir tabelaya t?kla!");
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arena bulunamad?!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw tabelaekle <arena>");
                    }
                } elseif (strtolower($args[0]) == "haritakaydet" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        $arena = $args[1];
                        if ($this->arenaExists($arena)) {
                            $this->saveMaps($arena);
                            $sender->sendMessage(TextFormat::GREEN . "Ba?ar?yla" . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenan?n bulundu?u d�nyay? kaydettin!");
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arena bulunamad?!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw haritakaydet <arena>");
                    }
                } elseif (strtolower($args[0]) == "arenaekle" && $sender->isOP()) {
                    if (!empty($args[1]) && !empty($args[2]) && !empty($args[3])) {
                        $arena = $args[1];
                        $teams = (int)$args[2];
                        $ppt = (int)$args[3];
                        if ($teams <= 8) {
                            $this->createArena($arena, $teams, $ppt);
                            $this->arena = $arena;
                            $sender->sendMessage(TextFormat::GREEN . "Ba?ar?yla " . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenay? ekledin!");
                            $sender->sendMessage(TextFormat::GREEN . "Kurulum -> /bw yardim");
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arenaya maksimum 8 tak?m ekleyebilirsin!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw arenaekle <arena> <tak?msay?s?> <tak?mba??naoyuncusay?s?>");
                    }
                } elseif (strtolower($args[0]) == "lobiekle" && $sender->isOP()) {
                    if (!empty($args[1])) {
                        $arena = $args[1];
                        if ($this->arenaExists($arena)) {
                            $this->setLobby($arena, $sender);
                            $sender->sendMessage(TextFormat::GREEN . "Ba?ar?yla " . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenaya lobi ekledin!");
                            $sender->sendMessage(TextFormat::GREEN . "Kurulum -> /bw yardim");
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arena bulunamad?!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw lobiekle <arena>");
                    }
                } elseif (strtolower($args[0]) == "yatakekle" && $sender->isOP()) {
                    if (!empty($args[1]) && !empty($args[2])) {
                        $arena = $args[1];
                        $team = $args[2];
                        if ($this->arenaExists($arena)) {
                            if (in_array($team, $this->getAllTeams())) {
                                $this->registerBed = true;
                                $this->registerBedWHO = $name;
                                $this->registerBedArena = $arena;
                                $this->registerBedTeam = $team;
                                $sender->sendMessage(TextFormat::GREEN . "Bir yata??n alt k?sm?na t?kla!");
                                $this->resetArena($arena);
                            } else {
                                $alleteams = implode(" ", $this->getAllTeams());
                                $sender->sendMessage(TextFormat::YELLOW . $team . TextFormat::RED . " tak?m bulunamad?!");
                                $sender->sendMessage(TextFormat::RED . "T�m tak?mlar: " . TextFormat::YELLOW . $alleteams);
                            }
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arena bulunamad?!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw yatakekle <arena> <tak?m>");
                    }
                } elseif (strtolower($args[0]) == "takimekle" && $sender->isOP()) {
                    if (!empty($args[1]) && !empty($args[2])) {
                        $arena = $args[1];
                        $team = $args[2];
                        if ($this->arenaExists($arena)) {
                            if (in_array($team, $this->getAllTeams())) {
                                $this->setSpawn($arena, $team, $sender);
                                $sender->sendMessage(TextFormat::GREEN . "Ba?ar?yla " . TextFormat::AQUA . $team . TextFormat::GREEN . " tak?m? " . TextFormat::AQUA . $arena . TextFormat::GREEN . " adl? arenaya ekledin!");
                                $sender->sendMessage(TextFormat::GREEN . "Kurulum -> /bw yardim");
                                $this->resetArena($arena);
                            } else {
                                $alleteams = implode(" ", $this->getAllTeams());
                                $sender->sendMessage(TextFormat::YELLOW . $team . TextFormat::RED . " tak?m bulunamad?!");
                                $sender->sendMessage(TextFormat::RED . "T�m tak?mlar: " . TextFormat::YELLOW . $alleteams);
                            }
                        } else {
                            $sender->sendMessage(TextFormat::RED . "Arena bulunamad?!");
                        }
                    } else {
                        $sender->sendMessage(TextFormat::RED . "/bw takimekle <arena> <takim>");
                    }
                } elseif (strtolower($args[0]) == "test" && $sender->isOP()) {
                    $this->createVillager($sender->getX(), $sender->getY(), $sender->getZ(), $sender->getLevel());
                } else {
                    $this->getServer()->dispatchCommand($sender, "bw yardim");
                }
            } else {
                $this->getServer()->dispatchCommand($sender, "bw yardim");
            }
        }
    }
}


class BWRefreshSigns extends PluginTask {
    public $prefix = "";
    public $plugin;
    
	
	public function __construct(Bedwars $plugin) {
        $this->plugin = $plugin;
        $this->prefix = $this->plugin->prefix;
        parent::__construct($plugin);
    }
    
	
	public function onRun($tick) {
        $levels = $this->plugin->getServer()->getDefaultLevel();
        $tiles = $levels->getTiles();
        foreach ($tiles as $t) {
            if ($t instanceof Sign) {
                $text = $t->getText();
                if ($text[0] == $this->prefix) {
                    $arena = substr($text[1], 0, -4);
                    $config = new Config($this->plugin->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                    $players = $this->plugin->getPlayers($arena);
                    $status = $config->get("Status");
                    $welt = $this->plugin->getArenaWorlds($arena) [0];
                    $level = $this->plugin->getServer()->getLevelByName($welt);
                    $arenasign = $text[1];
                    $teams = (int)$config->get("Teams");
                    $ppt = (int)$config->get("PlayersPerTeam");
                    $maxplayers = $teams * $ppt;
                    $ingame = TextFormat::GREEN . "Kat?l!";
                    if ($status != "Lobby") {
                        $ingame = TextFormat::RED . "Oyunda!";
                    }
                    if (count($players) >= $maxplayers) {
                        $ingame = TextFormat::RED . "Dolu!";
                    }
                    if ($status == "Ende") {
                        $ingame = TextFormat::RED . "Yenileniyor!";
                    }
                    $t->setText($this->prefix, $arenasign, $ingame, TextFormat::GREEN . (count($players)) . TextFormat::GRAY . "/" . TextFormat::RED . $maxplayers);
                }
            }
        }
    }
}


class BWGameSender extends PluginTask {
    public $prefix = "";
    public $plugin;
    public function __construct(Bedwars $plugin) {
        $this->plugin = $plugin;
        $this->prefix = $plugin->prefix;
        parent::__construct($plugin);
    }
    
	
	public function onRun($tick) {
        $files = scandir($this->plugin->getDataFolder() . "Arenas");
        foreach ($files as $filename) {
            if ($filename != "." && $filename != "..") {
                $arena = str_replace(".yml", "", $filename);
                $config = new Config($this->plugin->getDataFolder() . "Arenas/" . $arena . ".yml", Config::YAML);
                $cfg = new Config($this->plugin->getDataFolder() . "config.yml", Config::YAML);
                $players = $this->plugin->getPlayers($arena);
                $status = $config->get("Status");
                $teams = (int)$config->get("Teams");
                $ppt = (int)$config->get("PlayersPerTeam");
                $lobbytimer = (int)$config->get("LobbyTimer");
                $gametimer = (int)$config->get("GameTimer");
                $endtimer = (int)$config->get("EndTimer");
                $maxplayers = (int)$teams * $ppt;
                $welt = $this->plugin->getFigthWorld($arena);
                $level = $this->plugin->getServer()->getLevelByName($welt);
                $aliveTeams = $this->plugin->getAliveTeams($arena);
                $minplayers = $ppt + 1;
                if ($status == "Lobby") {
                    if (count($players) < $minplayers) {
                        if ((Time() % 10) == 0) {
                            $config->set("LobbyTimer", $cfg->get("LobbyTimer"));
                            $config->set("GameTimer", $cfg->get("GameTimer"));
                            $config->set("EndTimer", $cfg->get("EndTimer"));
                            $config->set("Status", "Lobby");
                            $config->save();
                        }
                        foreach ($players as $pn) {
                            $p = $this->plugin->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                $p->sendPopup(TextFormat::RED . "En az " . TextFormat::YELLOW . $minplayers . TextFormat::RED . " oyuncu gereklidir!");
                            } else {
                                $this->plugin->removePlayerFromArena($arena, $pn);
                            }
                        }
                        if ((Time() % 20) == 0) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendMessage(TextFormat::RED . "En az " . TextFormat::YELLOW . $minplayers . TextFormat::RED . " oyuncu gereklidir!");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                    } else {
                        $lobbytimer--;
                        $config->set("LobbyTimer", $lobbytimer);
                        $config->save();
                        if ($lobbytimer == 60 || $lobbytimer == 45 || $lobbytimer == 30 || $lobbytimer == 20 || $lobbytimer == 10) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendTitle(("�aBa?lamas?na "), ("�e$lobbytimer �asaniye!"));
                                }
                            }
                        }
                        if ($lobbytimer >= 1 && $lobbytimer <= 5) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendTitle(("�aBa?lamas?na "), ("�e$lobbytimer �asaniye!"));
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        if ($lobbytimer == 0) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    if ($p->getNameTag() == $p->getName()) {
                                        $AT = $this->plugin->getAvailableTeam($arena);
                                        $p->setNameTag($this->plugin->getTeamColor($AT) . $pn);
                                    }
                                    $this->plugin->TeleportToTeamSpawn($p, $this->plugin->getTeam($p->getNameTag()), $arena);
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                            $config->set("Status", "Ingame");
                            $config->save();
                        }
                    }
                } elseif ($status == "Ingame") {
                    if (count($aliveTeams) <= 1) {
                        if (count($aliveTeams) == 1) {
                            $winnerteam = $aliveTeams[0];
                            $this->plugin->getServer()->broadcastMessage($this->prefix . TextFormat::YELLOW . $winnerteam . TextFormat::GREEN . " tak?m oyunu " . TextFormat::YELLOW . $arena . TextFormat::GREEN . " adl? arenada kazand?!");
                        }
                        $config->set("Status", "Ende");
                        $config->save();
                    } else {
                        if ((Time() % 1) == 0) {
                            $tiles = $level->getTiles();
                            foreach ($tiles as $tile) {
                                if ($tile instanceof Sign) {
                                    $text = $tile->getText();
                                    if (strtolower($text[0]) == "bronz" || strtolower($text[1]) == "bronz" || strtolower($text[2]) == "bronz" || strtolower($text[3]) == "bronz") {
                                        $loc = new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5);
                                        $needDrop = false;
                                        foreach ($players as $pn) {
                                            $p = $this->plugin->getServer()->getPlayerExact($pn);
                                            if ($p != null) {
                                                $dis = $loc->distance($p);
                                                if ($dis <= 10) {
                                                    $needDrop = true;
                                                }
                                            }
                                        }
                                        if ($needDrop === true) {
                                            $level->dropItem(new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5), Item::get(Item::BRICK, 0, 1));
                                            $level->dropItem(new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5), Item::get(Item::BRICK, 0, 1));
                                        }
                                    }
                                }
                            }
                        }
                        if ((Time() % 8) == 0) {
                            $tiles = $level->getTiles();
                            foreach ($tiles as $tile) {
                                if ($tile instanceof Sign) {
                                    $text = $tile->getText();
                                    if (strtolower($text[0]) == "demir" || strtolower($text[1]) == "demir" || strtolower($text[2]) == "demir" || strtolower($text[3]) == "demir") {
                                        $level->dropItem(new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5), Item::get(Item::IRON_INGOT, 0, 1));
                                    }
                                }
                            }
                        }
                        if ((Time() % 60) == 0) {
                            $tiles = $level->getTiles();
                            foreach ($tiles as $tile) {
                                if ($tile instanceof Sign) {
                                    $text = $tile->getText();
                                    if (strtolower($text[0]) == "elmas" || strtolower($text[1]) == "elmas" || strtolower($text[2]) == "elmas" || strtolower($text[3]) == "elmas") {
                                        $level->dropItem(new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5), Item::get(Item::DIAMOND, 0, 1));
                                    }
                                }
                            }
                        }
                        if ((Time() % 30) == 0) {
                            $tiles = $level->getTiles();
                            foreach ($tiles as $tile) {
                                if ($tile instanceof Sign) {
                                    $text = $tile->getText();
                                    if (strtolower($text[0]) == "altin" || strtolower($text[1]) == "altin" || strtolower($text[2]) == "altin" || strtolower($text[3]) == "altin") {
                                        $level->dropItem(new Vector3($tile->getX() + 0.5, $tile->getY() + 2, $tile->getZ() + 0.5), Item::get(Item::GOLD_INGOT, 0, 1));
                                    }
                                }
                            }
                        }
                        foreach ($players as $pn) {
                            $p = $this->plugin->getServer()->getPlayerExact($pn);
                            if ($p != null) {
                                $this->plugin->sendIngameScoreboard($p, $arena);
                            } else {
                                $this->plugin->removePlayerFromArena($arena, $pn);
                            }
                        }
                        $gametimer--;
                        $config->set("GameTimer", $gametimer);
                        $config->save();
                        if ($gametimer == 900 || $gametimer == 600 || $gametimer == 300 || $gametimer == 240 || $gametimer == 180) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendMessage($this->plugin->prefix . TextFormat::YELLOW . $gametimer / 60 . TextFormat::RED . " dakika kald?!");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        } elseif ($gametimer == 2 || $gametimer == 3 || $gametimer == 4 || $gametimer == 5 || $gametimer == 15 || $gametimer == 30 || $gametimer == 60) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendMessage($this->plugin->prefix . TextFormat::YELLOW . $gametimer / 60 . TextFormat::RED . " saniye kald?!");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        } elseif ($gametimer == 1) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendMessage($this->plugin->prefix . TextFormat::YELLOW . " 1" . TextFormat::RED . " saniye kald?!");
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        } elseif ($gametimer == 0) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendMessage($this->plugin->prefix . TextFormat::RED . "Deathmatch ba?lad?!");
                                    $p->sendMessage($this->plugin->prefix . TextFormat::RED . "Kazanan olmad?!");
                                    $config->set($arena . "Status", "Ende");
                                    $config->save();
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                    }
                } elseif ($status == "Ende") {
                    if ($endtimer >= 0) {
                        $endtimer--;
                        $config->set("EndTimer", $endtimer);
                        $config->save();
                        if ($endtimer == 15 || $endtimer == 10 || $endtimer == 5 || $endtimer == 4 || $endtimer == 3 || $endtimer == 2 || $endtimer == 1) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->sendTitle(("�cArenan?n"), ("�cyenilenmesine �e$endtimer �csaniye!"));
                                } else {
                                    $this->plugin->removePlayerFromArena($arena, $pn);
                                }
                            }
                        }
                        if ($endtimer == 0) {
                            foreach ($players as $pn) {
                                $p = $this->plugin->getServer()->getPlayerExact($pn);
                                if ($p != null) {
                                    $p->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
                                    $p->setFood(20);
                                    $p->setHealth(20);
                                    $p->getInventory()->clearAll();
                                    $p->removeAllEffects();
                                    $p->setNameTag($p->getName());
                                }
                            }
                            $this->plugin->resetArena($arena, true);
                        }
                    }
                }
            }
        }
    }
}