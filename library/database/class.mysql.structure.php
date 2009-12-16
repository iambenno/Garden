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
 * The MySQLStructure class is a MySQL-specific class for manipulating
 * database structure.
 *
 * @author Mark O'Sullivan
 * @copyright 2003 Mark O'Sullivan
 * @license http://www.opensource.org/licenses/gpl-2.0.php GPL
 * @package Garden
 * @version @@GARDEN-VERSION@@
 * @namespace Garden.Database
 */
require_once(dirname(__FILE__).DS.'class.databasestructure.php');

class Gdn_MySQLStructure extends Gdn_DatabaseStructure {
   /// Constructor ///
   
   public function __construct($Database = NULL) {
      parent::__construct($Database);
   }

   /**
    * Drops $this->Table() from the database.
    */
   public function Drop() {
      return $this->Database->Query('drop table `'.$this->_DatabasePrefix.$this->_TableName.'`');
   }

   /**
    * Drops $Name column from $this->Table().
    *
    * @param string $Name The name of the column to drop from $this->Table().
    * @return boolean
    */
   public function DropColumn($Name) {
      if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` drop column `'.$Name.'`'))
         throw new Exception(Gdn::Translate('Failed to remove the `'.$Name.'` column from the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));

      return TRUE;
   }

   /**
    * Renames a column in $this->Table().
    *
    * @param string $OldName The name of the column to be renamed.
    * @param string $NewName The new name for the column being renamed.
    * @param string $TableName
    * @return boolean
    * @todo $TableName needs a description.
    */
   public function RenameColumn($OldName, $NewName, $TableName = '') {
      if ($TableName != '')
         $this->_TableName = $TableName;

      // Get the schema for this table
      $OldPrefix = $this->Database->DatabasePrefix;
      $this->Database->DatabasePrefix = $this->_DatabasePrefix;
      $Schema = $this->Database->SQL()->FetchTableSchema($this->_TableName);
      $this->Database->DatabasePrefix = $OldPrefix;

      // Get the definition for this column
      $OldColumn = ArrayValue($OldName, $Schema);
      $NewColumn = ArrayValue($NewName, $Schema);

      // Make sure that one column, or the other exists
      if (!$OldColumn && !$NewColumn)
         throw new Exception(Gdn::Translate('The `'.$OldName.'` column does not exist.'));

      // Make sure the new column name isn't already taken
      if ($OldColumn && $NewColumn)
         throw new Exception(Gdn::Translate('You cannot rename the `'.$OldName.'` column to `'.$NewName.'` because that column already exists.'));

      // Rename the column
      // The syntax for renaming a column is:
      // ALTER TABLE tablename CHANGE COLUMN oldname newname originaldefinition;
      if (!$this->Database->Query('alter table `'.$this->_TableName.'` change column `'.$OldName.'` `'.$NewName.'` '.$this->_DefineColumn($OldColumn)))
         throw new Exception(Gdn::Translate('Failed to rename table `'.$OldName.'` to `'.$NewName.'`.'));

      return TRUE;
   }

   /**
    * Renames a table in the database.
    *
    * @param string $OldName The name of the table to be renamed.
    * @param string $NewName The new name for the table being renamed.
    * @param boolean $UsePrefix A boolean value indicating if $this->_DatabasePrefix should be prefixed
    * before $OldName and $NewName.
    * @return boolean
    */
   public function RenameTable($OldName, $NewName, $UsePrefix = FALSE) {
      if (!$this->Database->Query('rename table `'.$OldName.'` to `'.$NewName.'`'))
         throw new Exception(Gdn::Translate('Failed to rename table `'.$OldName.'` to `'.$NewName.'`.'));

      return TRUE;
   }

   /**
    * Specifies the name of the view to create or modify.
    *
    * @param string $Name The name of the view.
    * @param string $Query The actual query to create as the view. Typically
    * this can be generated with the $Database object.
    */
   public function View($Name, $SQL) {
      if(is_string($SQL)) {
         $SQLString = $SQL;
         $SQL = NULL;
      } else {
         $SQLString = $SQL->GetSelect();
      }
      
      $Result = $this->Database->Query('create or replace view '.$this->_DatabasePrefix.$Name." as \n".$SQLString);
      if(!is_null($SQL)) {
         $SQL->Reset();
      }
   }

   /**
    * Creates the table defined with $this->Table() and $this->Column().
    */
   protected function _Create() {
      $PrimaryKey = array();
      $UniqueKey = array();
      $Keys = '';
      $Sql = '';

      foreach ($this->_Columns as $ColumnName => $Column) {
         if ($Sql != '')
            $Sql .= ',';

         $Sql .= "\n".$this->_DefineColumn($Column);

         if ($Column->KeyType == 'primary')
            $PrimaryKey[] = $ColumnName;
         elseif ($Column->KeyType == 'key')
            $Keys .= ",\nkey `".Format::AlphaNumeric('`FK_'.$this->_TableName.'_'.$ColumnName).'` (`'.$ColumnName.'`)';
         elseif ($Column->KeyType == 'index')
            $Keys .= ",\nindex `".Format::AlphaNumeric('`IX_'.$this->_TableName.'_'.$ColumnName).'` (`'.$ColumnName.'`)';
         elseif ($Column->KeyType == 'unique')
            $UniqueKey[] = $ColumnName;
      }
      // Build primary keys
      if (count($PrimaryKey) > 0)
         $Keys .= ",\nprimary key (`".implode('`, `', $PrimaryKey)."`)";
      // Build unique keys.
      if (count($UniqueKey) > 0)
         $Keys .= ",\nunique index `".Format::AlphaNumeric('`UX_'.$this->_TableName).'` (`'.implode('`, `', $UniqueKey)."`)";

      $Sql = 'create table `'.$this->_DatabasePrefix.$this->_TableName.'` ('
         .$Sql
         .$Keys
      ."\n)";

      if ($this->_CharacterEncoding !== FALSE && $this->_CharacterEncoding != '')
         $Sql .= ' default character set '.$this->_CharacterEncoding;
         
      if (array_key_exists('Collate', $this->Database->ExtendedProperties)) {
         $Sql .= ' collate ' . $this->Database->ExtendedProperties['Collate'];
      }

      $Result = $this->Database->Query($Sql);
      $this->_Reset();
      
      return $Result;
   }

   /**
    * Modifies $this->Table() with the columns specified with $this->Column().
    *
    * @param boolean $Explicit If TRUE, this method will remove any columns from the table that were not
    * defined with $this->Column().
    */
   protected function _Modify($Explicit = FALSE) {
      // Get the columns from the table
      $ExistingColumns = $this->Database->SQL()->FetchColumns($this->_DatabasePrefix.$this->_TableName);

      // 1. Remove any unnecessary columns if this is an explicit modification
      if ($Explicit) {
         // array_diff returns values from the first array that aren't present
         // in the second array. In this example, all columns currently in the
         // table that are NOT in $this->_Columns.
         $RemoveColumns = array_diff($ExistingColumns, array_keys($this->_Columns));
         foreach ($RemoveColumns as $Column) {
            $this->DropColumn($Column);
         }
      }

      // 2. Add new columns

      // array_diff returns values from the first array that aren't present in
      // the second array. In this example, all columns in $this->_Columns that
      // are NOT in the table.
      $NewColumns = array_diff(array_keys($this->_Columns), $ExistingColumns);
      foreach ($NewColumns as $Column) {
         if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` add '.$this->_DefineColumn(ArrayValue($Column, $this->_Columns))))
            throw new Exception(Gdn::Translate('Failed to add the `'.$Column.'` column to the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));

         // Add keys if necessary
         $Col = ArrayValue($Column, $this->_Columns);
         if ($Col->KeyType == 'primary') {
            if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` add primary key using btree(`'.$Column.'`)'))
               throw new Exception(Gdn::Translate('Failed to add the `'.$Column.'` primary key to the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));
         } else if ($Col->KeyType == 'key') {
            if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` add index `'.Format::AlphaNumeric('`FK_'.$this->_TableName.'_'.$Column).'` (`'.$Column.'`)'))
               throw new Exception(Gdn::Translate('Failed to add the `'.$Column.'` key to the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));
         } else if ($Col->KeyType == 'index') {
            if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` add index `'.Format::AlphaNumeric('`IX_'.$this->_TableName.'_'.$Column).'` (`'.$Column.'`)'))
               throw new Exception(Gdn::Translate('Failed to add the `'.$Column.'` index to the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));
         } else if ($Col->KeyType == 'unique') {
            if (!$this->Database->Query('alter table `'.$this->_DatabasePrefix.$this->_TableName.'` add unique index `'.Format::AlphaNumeric('`UX_'.$this->_TableName.'_'.$Column).'` (`'.$Column.'`)'))
               throw new Exception(Gdn::Translate('Failed to add the `'.$Column.'` unique index to the `'.$this->_DatabasePrefix.$this->_TableName.'` table.'));
         }
      }

      $this->_Reset();
      return TRUE;
   }

   /**
    * Undocumented method.
    *
    * @param string $Column
    * @todo This method and $Column need descriptions.
    */
   protected function _DefineColumn($Column) {
      if (!is_array($Column->Type) && !in_array($Column->Type, array('tinyint', 'smallint', 'int', 'char', 'varchar', 'varbinary', 'datetime', 'text', 'decimal', 'enum')))
         throw new Exception(Gdn::Translate('The specified data type ('.$Column->Type.') is not accepted for the MySQL database.'));
      
      $Return = '`'.$Column->Name.'` '.$Column->Type;
      if ($Column->Length != '') {
         if($Column->Precision != '')
            $Return .= '('.$Column->Length.', '.$Column->Precision.')';
         else
            $Return .= '('.$Column->Length.')';
      }
      if (property_exists($Column, 'Unsigned') && $Column->Unsigned) {
         $Return .= ' unsigned';
      }

      if (is_array($Column->Enum))
         $Return .= "('".implode("','", $Column->Enum)."')";

      if (!$Column->AllowNull)
         $Return .= ' not null';

      if (!is_null($Column->Default))
         $Return .= " default ".self::_QuoteValue($Column->Default);

      if ($Column->AutoIncrement)
         $Return .= ' auto_increment';

      return $Return;
   }
   
   protected static function _QuoteValue($Value) {
      if(is_numeric($Value)) {
         return $Value;
      } else if(is_bool($Value)) {
         return $Value ? '1' : '0';
      } else {
         return "'".str_replace("'", "''", $Value)."'";
      }
   }
}