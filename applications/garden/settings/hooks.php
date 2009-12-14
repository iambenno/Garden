<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Mark O'Sullivan
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Mark O'Sullivan at mark [at] lussumo [dot] com
*/

class GardenHooks implements Gdn_IPlugin {
   public function Setup() {
      return TRUE;
   }
   
   public function Base_Render_Before(&$Sender) {
      $Session = Gdn::Session();

      // Enable theme previewing
      if ($Session->IsValid()) {
         $PreviewTheme = $Session->GetPreference('PreviewTheme', '');
         if ($PreviewTheme != '')
            $Sender->Theme = $PreviewTheme;
      }

      // Add Message Modules (if necessary)
      $MessageCache = Gdn::Config('Garden.Messages.Cache', array());
      $Location = $Sender->Application.'/'.substr($Sender->ControllerName, 0, -10).'/'.$Sender->RequestMethod;
      if ($Sender->MasterView != 'empty' && in_array('Base', $MessageCache) || InArrayI($Location, $MessageCache)) {
         $MessageModel = new Gdn_MessageModel();
         $MessageData = $MessageModel->GetMessagesForLocation($Location);
         foreach ($MessageData as $Message) {
            $MessageModule = new Gdn_MessageModule($Sender, $Message);
            $Sender->AddModule($MessageModule);
         }
      }
   }
   
   public function Base_GetAppSettingsMenuItems_Handler(&$Sender) {
      $Menu = &$Sender->EventArguments['SideMenu'];
      $Menu->AddItem('Dashboard', Gdn::Translate('Dashboard'));
      $Menu->AddLink('Dashboard', Gdn::Translate('Dashboard'), 'garden/settings', 'Garden.Settings.Manage');

      $Menu->AddItem('Site Settings', Gdn::Translate('Site Settings'));
      $Menu->AddLink('Site Settings', Gdn::Translate('General'), 'garden/settings/configure', 'Garden.Settings.Manage');
      $Menu->AddLink('Site Settings', Gdn::Translate('Routes'), 'garden/routes', 'Garden.Routes.Manage');
      $Menu->AddLink('Site Settings', Gdn::Translate('Messages'), 'garden/message', 'Garden.Messages.Manage');
      
      $Menu->AddItem('Add-ons', Gdn::Translate('Add-ons'));
      $Menu->AddLink('Add-ons', Gdn::Translate('Applications'), 'garden/settings/applications', 'Garden.Applications.Manage');
      $Menu->AddLink('Add-ons', Gdn::Translate('Plugins'), 'garden/settings/plugins', 'Garden.Applications.Manage');
      $Menu->AddLink('Add-ons', Gdn::Translate('Themes'), 'garden/settings/themes', 'Garden.Themes.Manage');

      $Menu->AddItem('Users', Gdn::Translate('Users'));
      $Menu->AddLink('Users', Gdn::Translate('Users'), 'garden/user', array('Garden.Users.Add', 'Garden.Users.Edit', 'Garden.Users.Delete'));
      $Menu->AddLink('Users', Gdn::Translate('Roles & Permissions'), 'garden/role', 'Garden.Roles.Manage');
      $Menu->AddLink('Users', Gdn::Translate('Registration'), 'garden/settings/registration', 'Garden.Registration.Manage');
      if (Gdn::Config('Garden.Registration.Method') == 'Approval')
         $Menu->AddLink('Users', Gdn::Translate('Applicants'), 'garden/user/applicants', 'Garden.Applicants.Manage');
   }
}