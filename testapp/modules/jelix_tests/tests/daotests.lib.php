<?php
/**
* @package     testapp
* @subpackage  jelix_tests module
* @author      Laurent Jouanneau
* @contributor
* @copyright   2009 Laurent Jouanneau
* @link        http://jelix.org
* @licence     GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

require_once(JELIX_LIB_PATH.'dao/jDaoCompiler.class.php');
require_once(JELIX_LIB_PATH.'plugins/db/mysql/mysql.daobuilder.php');
include_once (JELIX_LIB_PATH.'plugins/db/mysql/mysql.dbtools.php');


class fakejSelectorDao extends jSelectorDao {
  
  function __construct($module='', $resource='', $driver='mysql') {
      $this->driver = $driver;
      $this->_compiler = 'jDaoCompiler';
      $this->_compilerPath = JELIX_LIB_PATH.'dao/jDaoCompiler.class.php';
      $this->module = $module;
      $this->resource = $resource;
      $this->_path = '';
      $this->_where =   '';
  }
}

class testMysqlDaoGenerator extends mysqlDaoBuilder {

    function GetPropertiesBy ($captureMethod){
        return $this->_getPropertiesBy ($captureMethod);
    }

    function BuildSimpleConditions (&$fields, $fieldPrefix='', $forSelect=true){
        return $this->_buildSimpleConditions ($fields, $fieldPrefix, $forSelect);
    }

    function BuildConditions($cond, $fields, $params=array(), $withPrefix=true, $groupby='') {
        return $this->_buildConditions ($cond, $fields, $params, $withPrefix, $groupby);
    }

    function BuildSQLCondition ($condition, $fields, $params, $withPrefix){
        return $this->_buildSQLCondition ($condition, $fields, $params, $withPrefix, true);
    }

    function GetPreparePHPValue($value, $fieldType, $checknull=true){
        return $this->tools->escapeValue($fieldType, $value, $checknull, true);
    }

    function GetPreparePHPExpr($expr, $fieldType, $checknull=true, $forCondition=''){
        return $this->_preparePHPExpr($expr, $fieldType, $checknull, $forCondition);
    }

    function GetSelectClause ($distinct=false){
        return $this->_getSelectClause ($distinct);
    }

    function GetFromClause(){
        return $this->_getFromClause();
    }
}


class testDaoProperty {
    public $datatype;
    public $unifiedType;
    public $defaultValue=null;
    public $autoIncrement = false;
}


class testjDaoParser extends jDaoParser {
  
  function testParseDatasource($xml) {
      $this->parseDatasource($xml);
  }
  function testParseRecord($xml, $tools) {
      $this->parseRecord($xml, $tools);
  }
  function testParseFactory($xml) {
      $this->parseFactory($xml);
  }
}
