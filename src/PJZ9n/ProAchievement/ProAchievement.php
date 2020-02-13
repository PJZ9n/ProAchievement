<?php

/**
 * Copyright (c) 2020 PJZ9n.
 *
 * This file is part of ProAchievement.
 *
 * ProAchievement is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * ProAchievement is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ProAchievement.  If not, see <http://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace PJZ9n\ProAchievement;

use Particle\Filter\Filter;
use Particle\Validator\Validator;
use pocketmine\lang\BaseLang;
use pocketmine\plugin\PluginBase;
use RuntimeException;

class ProAchievement extends PluginBase
{
    
    /** @var string */
    private static $composerAutoloaderPath = null;
    
    /**
     * @return string
     */
    public static function getComposerAutoloaderPath(): string
    {
        if (self::$composerAutoloaderPath === null) {
            self::$composerAutoloaderPath = __DIR__ . "/../../../vendor/autoload.php";
        }
        return self::$composerAutoloaderPath;
    }
    
    /** @var string */
    private $localePath;
    
    /** @var BaseLang */
    private $lang;
    
    public function onLoad(): void
    {
        require_once self::getComposerAutoloaderPath();
        
        $this->loadLanguage();
        $this->loadConfig();
    }
    
    public function onEnable(): void
    {
        $this->initLanguage();
        $this->initConfig();
        
        $this->getLogger()->info($this->lang->translateString("plugin.license", [$this->getDescription()->getName()]));
    }
    
    private function loadLanguage(): void
    {
        if (!file_exists($this->getDataFolder() . "locale/")) {
            mkdir($this->getDataFolder() . "locale/");
        }
        foreach ($this->getResources() as $resource) {
            if (strpos($resource->getPath(), "resources/locale") === false || $resource->getExtension() !== "ini") {
                continue;
            }
            $this->localePath = $resource->getPath() . "/";
            break;
        }
    }
    
    private function loadConfig(): void
    {
        $this->saveDefaultConfig();
        $this->reloadConfig();
        //Validation
        $validator = new Validator();
        $validator->required("config-version")->integer()->between(0, PHP_INT_MAX);
        $validator->required("lang")->string();
        $validateResult = $validator->validate($this->getConfig()->getAll());
        if ($validateResult->isNotValid()) {
            $errors = [];
            foreach ($validateResult->getFailures() as $failure) {
                $errors[] = $failure->format();
            }
            $this->getLogger()->error("Invalid config file: " . implode(" | ", $errors));
            throw new RuntimeException("Unable to load config file.");
        }
        //Filter
        $filter = new Filter();
        $filter->value("config-version")->int();
        $filter->value("lang")->string();
        $filterResult = $filter->filter($this->getConfig()->getAll());
        $this->getConfig()->setAll($filterResult);
    }
    
    private function initLanguage(): void
    {
        $lang = $this->getConfig()->get("lang");
        if ($lang === "default") {
            $lang = $this->getServer()->getLanguage()->getLang();
        }
        $this->lang = new BaseLang($lang, $this->localePath, "jpn");
        $this->getLogger()->info($this->lang->translateString("language.selected", [
            $this->lang->getName(),
            $this->lang->getLang(),
        ]));
    }
    
    private function initConfig(): void
    {
        //Check Config Version
        $latestConfig = yaml_parse(stream_get_contents($this->getResource("config.yml")));
        if (!isset($latestConfig["config-version"])) {
            $this->getLogger()->error($this->lang->translateString("config.load.error"));
            throw new RuntimeException("Unable to init config file.");
        }
        $latestConfigVersion = $latestConfig["config-version"];
        $nowConfigVersion = $this->getConfig()->get("config-version");
        if ($nowConfigVersion < $latestConfigVersion) {
            //Config update found
            $this->getLogger()->notice($this->lang->translateString("config.version.update.available"));
            $this->getConfig()->setDefaults($latestConfig);
            $this->getConfig()->set("config-version", $latestConfigVersion);
            $this->saveConfig();
            $this->getConfig()->reload();
            $this->getLogger()->info($this->lang->translateString("config.version.update.success"));
        } else if ($nowConfigVersion > $latestConfigVersion) {
            //Config unknown version
            $this->getLogger()->notice($this->lang->translateString("config.version.unknown"));
        } else {
            //Config is latest
            $this->getLogger()->info($this->lang->translateString("config.version.latest"));
        }
        //Replace comments
        $beforeInject = $this->getConfig()->getAll();
        foreach ($this->getConfig()->getAll() as $key => $value) {
            if (strpos($key, "//") !== 0) {
                continue;
            }
            if (!is_string($value)) {
                continue;
            }
            $this->getConfig()->set($key, $this->lang->translateString($value));
        }
        if ($this->getConfig()->getAll() !== $beforeInject) {
            $this->getConfig()->save();
        }
    }
    
}