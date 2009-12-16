<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

/**
 * Garden.Core
 */

/**
 * A singleton class used to identify extensions, register them in a central
 * location, and instantiate/call them when necessary.
 */
class Gdn_PluginManager {
   /**
    * An associative array of arrays containing information about each
    * enabled plugin. This value is assigned in the garden bootstrap.php.
    */
   public $EnabledPlugins = array();

   /**
    * An associative array of EventHandlerName => PluginName pairs.
    */
   private $_EventHandlerCollection = array();

   /**
    * An associative array of MethodOverrideName => PluginName pairs.
    */
   private $_MethodOverrideCollection = array();
   
   /**
    * An associative array of NewMethodName => PluginName pairs.
    */
   private $_NewMethodCollection = array();
   
   /**
    * An array of available plugins. Never access this directly, instead use
    * $this->AvailablePlugins();
    */
   private $_AvailablePlugins = NULL;
   
   /**
    * Register all enabled plugins
    *
    * Examines all declared classes, identifying which ones implement
    * Gdn_IPlugin and registers all of their event handlers and method
    * overrides. It recognizes them because Handlers end with _Handler,
    * _Before, and _After and overrides end with "_Override". They are prefixed
    * with the name of the class and method (or event) to be handled or
    * overridden. For example:
    *  class MyPlugin implements Gdn_IPlugin {
    *   public function MyController_SignIn_After($Sender) {
    *      // Do something neato
    *   }
    *   public function Url_AppRoot_Override($WithDomain) {
    *      return "MyCustomAppRoot!";
    *   }
    *  }
    */
   public function RegisterPlugins() {
      // Loop through all declared classes looking for ones that implement iPlugin.
      // print_r(get_declared_classes());
      foreach(get_declared_classes() as $ClassName) {
         // Only implement the plugin if it implements the Gdn_IPlugin interface and
         // it has it's properties defined in $this->EnabledPlugins.
         if (in_array('Gdn_IPlugin', class_implements($ClassName))) {
            $ClassMethods = get_class_methods($ClassName);
            foreach ($ClassMethods as $Method) {
               $MethodName = strtolower($Method);
               // Loop through their individual methods looking for event handlers and method overrides.
               if (isset($MethodName[9])) {
                  if (substr($MethodName, -8) == '_handler' || substr($MethodName, -7) == '_before' || substr($MethodName, -6) == '_after') {
                     // Create a new array of handler class names if it doesn't exist yet.
                     if (array_key_exists($MethodName, $this->_EventHandlerCollection) === FALSE)
                        $this->_EventHandlerCollection[$MethodName] = array();

                     // Specify this class as a handler for this method if it hasn't been done yet.
                     if (in_array($ClassName, $this->_EventHandlerCollection[$MethodName]) === FALSE)
                        $this->_EventHandlerCollection[$MethodName][] = $ClassName;
                  } else if (substr($MethodName, -9) == '_override') {
                     // Throw an error if this method has already been overridden.
                     if (array_key_exists($MethodName, $this->_MethodOverrideCollection) === TRUE)
                        trigger_error(ErrorMessage('Any object method can only be overridden by a single plugin. The "'.$MethodName.'" override has already been assigned by the "'.$this->_MethodOverrideCollection[$MethodName].'" plugin. It cannot also be overridden by the "'.$ClassName.'" plugin.', 'PluginManager', 'RegisterPlugins'), E_USER_ERROR);

                     // Otherwise, specify this class as the source for the override.
                     $this->_MethodOverrideCollection[$MethodName] = $ClassName;
                  } else if (substr($MethodName, -7) == '_create') {
                     // Throw an error if this method has already been created.
                     if (array_key_exists($MethodName, $this->_NewMethodCollection) === TRUE)
                        trigger_error(ErrorMessage('New object methods must be unique. The new "'.$MethodName.'" method has already been assigned by the "'.$this->_NewMethodCollection[$MethodName].'" plugin. It cannot also be overridden by the "'.$ClassName.'" plugin.', 'PluginManager', 'RegisterPlugins'), E_USER_ERROR);

                     // Otherwise, specify this class as the source for the new method.
                     $this->_NewMethodCollection[$MethodName] = $ClassName;
                  }
               }
            }
         }
      }
   }
   
   /**
    * Transfer control to the plugins
    *
    * Looks through $this->_EventHandlerCollection for matching event
    * signatures to handle. If it finds any, it executes them in the order it
    * found them. It instantiates any plugins and adds them as properties to
    * this class (unless they were previously instantiated), and then calls
    * the handler in question.
    *
    * @param object The object that fired the event being handled.
    * @param string The name of the class that fired the event being handled.
    * @param string The name of the event being fired.
    * @return bool True if an event was executed.
    */
   public function CallEventHandlers(&$Sender, $ClassName, $EventName, $HandlerType = 'Handler') {
      $Return = FALSE;
      
      // Look through $this->_EventHandlerCollection for relevant handlers
      if ($this->CallEventHandler($Sender, strtolower($ClassName.'_'.$EventName.'_'.$HandlerType)))
         $Return = TRUE;

      if ($this->CallEventHandler($Sender, strtolower('Base_'.$EventName.'_'.$HandlerType)))
         $Return = TRUE;
      return $Return;
   }
   
   public function CallEventHandler(&$Sender, $Handler) {
      $Return = FALSE;
      if (array_key_exists($Handler, $this->_EventHandlerCollection)) {
         // Loop through the handlers and execute them
         foreach ($this->_EventHandlerCollection[$Handler] as $PluginName) {
            if (property_exists($this, $PluginName) === FALSE)
               $this->$PluginName = new $PluginName();
            if (array_key_exists($Handler, $Sender->Returns) === FALSE || is_array($Sender->Returns[$Handler]) === FALSE)
               $Sender->Returns[$Handler] = array();
            
            $Sender->Returns[$Handler][strtolower($PluginName)] = $this->$PluginName->$Handler($Sender, $Sender->EventArguments);
            $Return = TRUE;
         }
      }
      return $Return;
   }
   
   /**
    * Looks through $this->_MethodOverrideCollection for a matching method
    * signature to override. It instantiates any plugins and adds them as
    * properties to this class (unless they were previously instantiated), then
    * calls the method in question.
    *
    * @param object The object being worked on.
    * @param string The name of the class that called the method being overridden.
    * @param string The name of the method that is being overridden.
    * @return mixed Return value of overridden method.
    */
   public function CallMethodOverride(&$Sender, $ClassName, $MethodName) {
      $Return = FALSE;
      $OverrideMethodName = strtolower($ClassName.'_'.$MethodName.'_Override');
      $PluginName = $this->_MethodOverrideCollection[$OverrideMethodName];
      if (property_exists($this, $PluginName) === FALSE)
         $this->$PluginName = new $PluginName($Sender);
         
      return $this->$PluginName->$OverrideMethodName($Sender, $Sender->EventArguments);
   }
   
   /**
    * Checks to see if there are any plugins that override the method being
    * executed.
    *
    * @param string The name of the class that called the method being overridden.
    * @param string The name of the method that is being overridden.
    * @return bool True if an override exists.
    */
   public function HasMethodOverride($ClassName, $MethodName) {
      return array_key_exists(strtolower($ClassName.'_'.$MethodName.'_Override'), $this->_MethodOverrideCollection) ? TRUE : FALSE;
   }
   
   /**
    * Looks through $this->_NewMethodCollection for a matching method signature
    * to call. It instantiates any plugins and adds them as properties to this
    * class (unless they were previously instantiated), then calls the method
    * in question.
    *
    * @param object The object being worked on.
    * @param string The name of the class that called the method being created.
    * @param string The name of the method that is being created.
    * @return mixed Return value of new method.
    */
   public function CallNewMethod(&$Sender, $ClassName, $MethodName) {
      $Return = FALSE;
      $NewMethodName = strtolower($ClassName.'_'.$MethodName.'_Create');
      $PluginName = $this->_NewMethodCollection[$NewMethodName];
      if (property_exists($this, $PluginName) === FALSE)
         $this->$PluginName = new $PluginName($Sender);
         
      return $this->$PluginName->$NewMethodName($Sender, $Sender->RequestArgs);
   }
   
   /**
    * Checks to see if there are any plugins that create the method being
    * executed.
    *
    * @param string The name of the class that called the method being created.
    * @param string The name of the method that is being created.
    * @return True if method exists.
    */
   public function HasNewMethod($ClassName, $MethodName) {
      return array_key_exists(strtolower($ClassName.'_'.$MethodName.'_Create'), $this->_NewMethodCollection) ? TRUE : FALSE;
   }
   
   /**
    * Looks through the plugins directory for valid plugins and returns them
    * as an associative array of "PluginName" => "Plugin Info Array". It also
    * adds "Folder", and "ClassName" definitions to the Plugin Info Array for
    * each plugin.
    */
   public function AvailablePlugins() {
      if (!is_array($this->_AvailablePlugins)) {
         $PluginInfo = array();
         if ($FolderHandle = opendir(PATH_PLUGINS)) {
            if ($FolderHandle === FALSE)
               return $PluginInfo;
            
            // Loop through subfolders (ie. the actual plugin folders)
            while (($Item = readdir($FolderHandle)) !== FALSE) {
               if(in_array($Item, array('.', '..'))) {
                  continue;
               }
               
               $PluginPaths = SafeGlob(PATH_PLUGINS . DS . $Item . DS . '*plugin.php');
               $PluginPaths[] = PATH_PLUGINS . DS . $Item . DS . 'default.php';
               
               foreach($PluginPaths as $i => $PluginFile) {
                  if (file_exists($PluginFile)) {
                     // echo '<div>'.$PluginFile.'</div>';
                     // Find the $PluginInfo array
                     $Tokens = token_get_all(implode('', file($PluginFile)));
                     $InfoBuffer = FALSE;
                     $ClassBuffer = FALSE;
                     $ClassName = '';
                     $PluginInfoString = '';
                     foreach ($Tokens as $Key => $Token) {
                        if (is_array($Token))
                           $Token = $Token[1];
                           
                        if ($Token == '$PluginInfo') {
                           $InfoBuffer = TRUE;
                           $PluginInfoString = '';
                        }
                           
                        if ($InfoBuffer)
                           $PluginInfoString .= $Token;
                        
                        if ($Token == ';')
                           $InfoBuffer = FALSE;
                        
                        if ($Token == 'implements') {
                           $ClassBuffer = FALSE;
                           $ClassName = trim($ClassName);
                           break;
                        }
      
                        if ($ClassBuffer)
                           $ClassName .= $Token;
                           
                        if ($Token == 'class') {
                           $ClassBuffer = TRUE;
                           $ClassName = '';
                        }
                        
                     }
                     if ($PluginInfoString != '')
                        eval($PluginInfoString);
                        
                     $PluginInfoString = '';
                        
                     // Define the folder name and assign the class name for the newly added item
                     foreach ($PluginInfo as $PluginName => $Plugin) {
                        if (array_key_exists('Folder', $PluginInfo[$PluginName]) === FALSE) {
                           $PluginInfo[$PluginName]['Folder'] = $Item;
                           $PluginInfo[$PluginName]['ClassName'] = $ClassName;
                        }
                     }
                  }
               }
            }
            closedir($FolderHandle);
         }
         $this->_AvailablePlugins = $PluginInfo;
      }
      return $this->_AvailablePlugins;
   }
   
   public function EnabledPluginFolders() {
      $EnabledPlugins = Gdn::Config('EnabledPlugins', array());
      return array_values($EnabledPlugins);
   }
   
   public function EnablePlugin($PluginName, $Validation, $Setup = FALSE) {
      // 1. Make sure that the plugin's requirements are met
      // Required Plugins
      $AvailablePlugins = $this->AvailablePlugins();
      $RequiredPlugins = ArrayValue('RequiredPlugins', ArrayValue($PluginName, $AvailablePlugins, array()), FALSE);
      CheckRequirements($PluginName, $RequiredPlugins, $this->EnabledPlugins, 'plugin');
      
      // Required Themes
      $ThemeManager = new Gdn_ThemeManager();
      $EnabledThemes = $ThemeManager->EnabledThemeInfo();
      $RequiredThemes = ArrayValue('RequiredTheme', ArrayValue($PluginName, $AvailablePlugins, array()), FALSE);
      CheckRequirements($PluginName, $RequiredThemes, $EnabledThemes, 'theme');
      
      // Required Applications
      $ApplicationManager = new Gdn_ApplicationManager();
      $EnabledApplications = $ApplicationManager->EnabledApplications();
      $RequiredApplications = ArrayValue('RequiredApplications', ArrayValue($PluginName, $AvailablePlugins, array()), FALSE);
      CheckRequirements($PluginName, $RequiredApplications, $EnabledApplications, 'application');

      // 2. Include the plugin, instantiate it, and call it's setup method
      $PluginInfo = ArrayValue($PluginName, $AvailablePlugins, FALSE);
      $PluginFolder = ArrayValue('Folder', $PluginInfo, FALSE);
      if ($PluginFolder == '')
         throw new Exception(Gdn::Translate('The plugin folder was not properly defined.'));

      $PluginClassName = ArrayValue('ClassName', $PluginInfo, FALSE);
      if ($PluginFolder !== FALSE && $PluginClassName !== FALSE && class_exists($PluginClassName) === FALSE) {
         $this->IncludePlugins(array($PluginName => $PluginFolder));
         
         if (class_exists($PluginClassName)) {
            $Plugin = new $PluginClassName();
            $Plugin->Setup();
         }
      } elseif(class_exists($PluginClassName, FALSE) !== FALSE && $Setup === TRUE) {
         $Plugin = new $PluginClassName();
         $Plugin->Setup();
      }
      
      // 3. If setup succeeded, register any specified permissions
      $PermissionName = ArrayValue('RegisterPermissions', $PluginInfo, FALSE);
      if ($PermissionName != FALSE) {
         $PermissionModel = Gdn::PermissionModel();
         $PermissionModel->Define($PermissionName);
      }

      if (is_object($Validation) && count($Validation->Results()) > 0)
         return FALSE;

      // 4. If everything succeeded, add the plugin to the
      // $EnabledPlugins array in conf/plugins.php
      // $EnabledPlugins['PluginClassName'] = 'Plugin Folder Name';
      SaveToConfig('EnabledPlugins'.'.'.$PluginName, $PluginFolder);
      
      $ApplicationManager = new Gdn_ApplicationManager();
      $Locale = Gdn::Locale();
      $Locale->Set($Locale->Current(), $ApplicationManager->EnabledApplicationFolders(), $this->EnabledPluginFolders(), TRUE);
      return TRUE;
   }
   
   public function DisablePlugin($PluginName) {
      // 1. Check to make sure that no other enabled plugins rely on this one
      // Get all available plugins and compile their requirements
      foreach ($this->EnabledPlugins as $CheckingName => $CheckingInfo) {
         $RequiredPlugins = ArrayValue('RequiredPlugins', $CheckingInfo, FALSE);
         if (is_array($RequiredPlugins) && array_key_exists($PluginName, $RequiredPlugins) === TRUE) {
            throw new Exception(sprintf(Gdn::Translate('You cannot disable the %1$s plugin because the %2$s plugin requires it in order to function.'), $PluginName, $CheckingName));
         }
      }
      
      // 2. Disable it
      RemoveFromConfig('EnabledPlugins'.'.'.$PluginName);
         
      unset($this->EnabledPlugins[$PluginName]);
      
      // Redefine the locale manager's settings $Locale->Set($CurrentLocale, $EnabledApps, $EnabledPlugins, TRUE);
      $ApplicationManager = new Gdn_ApplicationManager();
      $Locale = Gdn::Locale();
      $Locale->Set($Locale->Current(), $ApplicationManager->EnabledApplicationFolders(), $this->EnabledPluginFolders(), TRUE);
   }
   
   /**
    * Includes all of the plugin files for enabled plugins.
    *
    * Files are included in from the roots of each plugin directory of they have the following names.
    * - default.php
    * - *plugin.php
    *
    * @param array $EnabledPlugins An array of plugins that should be included.
    * If this argument is null then all enabled plugins will be included.
    * @return array The plugin info array for all included plugins.
    */
   public function IncludePlugins($EnabledPlugins = NULL) {
      // Include all of the plugins.
      if(is_null($EnabledPlugins))
         $EnabledPlugins = Gdn::Config('EnabledPlugins', array());
      
      // Get a list of files to include.
      $Paths = array();
      foreach ($EnabledPlugins as $PluginName => $PluginFolder) {
         $Paths[] = PATH_PLUGINS . DS . $PluginFolder . DS . 'default.php';
         $Paths = array_merge($Paths, SafeGlob(PATH_PLUGINS . DS . $PluginFolder . DS . '*plugin.php'));
      }
      if (!is_array($Paths))
         $Paths = array();
      
      // Include all of the paths.
      $PluginInfo = array();
      foreach($Paths as $Path) {
         if(file_exists($Path))
            include($Path);
      }
      
      return $PluginInfo;
   }
}
