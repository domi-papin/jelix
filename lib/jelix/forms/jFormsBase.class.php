<?php
/**
* @package     jelix
* @subpackage  forms
* @author      Laurent Jouanneau
* @contributor Dominique Papin
* @contributor Bastien Jaillot
* @contributor Christophe Thiriot, Julien Issler, Olivier Demah
* @copyright   2006-2010 Laurent Jouanneau, 2007 Dominique Papin, 2008 Bastien Jaillot
* @copyright   2008-2009 Julien Issler, 2009 Olivier Demah
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 *
 */
require(JELIX_LIB_PATH.'forms/jFormsControl.class.php');
require(JELIX_LIB_PATH.'forms/jFormsDatasource.class.php');
require(JELIX_LIB_UTILS_PATH.'jDatatype.class.php');

/**
 * exception for jforms
 * @package     jelix
 * @subpackage  forms
 */
class jExceptionForms extends jException {

}

/**
 * base class of all form classes generated by the jform compiler
 * @package     jelix
 * @subpackage  forms
 */
abstract class jFormsBase {

    const SECURITY_LOW = 0;
    const SECURITY_CSRF = 1;

    public $securityLevel = 1;

    /**
     * List of all form controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $controls = array();

    /**
     * List of top controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $rootControls = array();

    /**
     * List of submit buttons
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $submits = array();

    /**
     * Reset button
     * @var jFormsControl
     * @see jFormsControl
     * @since 1.0
     */
    protected $reset = null;

    /**
     * List of uploads controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $uploads = array();

    /**
     * List of hidden controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $hiddens = array();

    /**
     * List of htmleditorcontrols
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $htmleditors = array();

    /**
     * List of wikieditorcontrols
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     * @since 1.2
     */
    protected $wikieditors = array();

    /**
     * the data container
     * @var jFormsDataContainer
     */
    protected $container = null;

    /**
     * content list of available form builder
     * @var boolean
     */
    protected $builders = array();

    /**
     * the form selector
     * @var string
     */
    protected $sel;

    /**
     * @param string $sel the form selector
     * @param jFormsDataContainer $container the data container
     * @param boolean $reset says if the data should be reset
     */
    public function __construct($sel, $container, $reset = false){
        $this->container = $container;
        if($reset){
            $this->container->clear();
        }
        $this->container->updatetime = time();
        $this->sel = $sel;
    }

    public function getSelector() {
        return $this->sel;
    }

    /**
     * set form data from request parameters
     */
    public function initFromRequest(){
        $req = $GLOBALS['gJCoord']->request;
        if ($this->securityLevel == jFormsBase::SECURITY_CSRF) {
            if ($this->container->token !== $req->getParam('__JFORMS_TOKEN__'))
                throw new jException("jelix~formserr.invalid.token");
        }

        foreach($this->rootControls as $name=>$ctrl){
            if(!$this->container->isActivated($name) || $this->container->isReadOnly($name))
                continue;
            $ctrl->setValueFromRequest($req);
        }
    }

    /**
     * check validity of all data form
     * @return boolean true if all is ok
     */
    public function check(){
        $this->container->errors = array();
        foreach($this->rootControls as $name=>$ctrl){
            if($this->container->isActivated($name))
                $ctrl->check();
        }
        return count($this->container->errors) == 0;
    }

    /**
     * prepare an object with values of all controls
     * @param object $object the object to fill
     * @param array $properties array of 'propertyname'=>array('required'=>true/false,
     *                          'defaultValue'=>$value, 'unifiedType'=>$datatype)
     *   values of datatype = same as jdb unified types
     */
    public function prepareObjectFromControls($object, $properties = null){
        if ($properties == null) {
            $properties = get_object_vars($object);
            foreach($properties as $n=>$v) {
                if (!is_null($v)) {
                    $r = true;
                    $t = gettype($v);
                }
                else {
                    $t = 'varchar';
                    $r = false;
                }
                $properties[$n]=array('required'=>$r, 'defaultValue'=>$v, 'unifiedType'=>$t);
            }
        }

        foreach($this->controls as $name=>$ctrl){
            if(!isset($properties[$name]))
                continue;

            if(is_array($this->container->data[$name])){
                if (count($this->container->data[$name]) ==1) {
                    $object->$name = $this->container->data[$name][0];
                }
                else
                    // do nothing for arrays ?
                    continue;
            }
            else{
                $object->$name = $this->container->data[$name];
            }

            if($object->$name == '' && !$properties[$name]['required']) {
                // if no value and if the property is not required, we set null to it
                $object->$name = null;
            }
            else {
                if (isset($properties[$name]['unifiedType']))
                    $type = $properties[$name]['unifiedType'];
                else
                    $type = $properties[$name]['datatype']; // for compatibility

                if($object->$name == '' && $properties[$name]['defaultValue'] !== null
                        && in_array($type,
                                    array('int','integer','double','float', 'numeric', 'decimal'))) {
                    $object->$name = $properties[$name]['defaultValue'];
                }
                else if( $type == 'boolean' && !is_bool($object->$name)) {
                    $object->$name = (intval($object->$name) == 1|| strtolower($object->$name) === 'true'
                                      || $object->$name === 't' || $object->$name === 'on');
                }
                else if($ctrl->datatype instanceof jDatatypeLocaleDateTime
                         && $type == 'datetime') {
                    $dt = new jDateTime();
                    $dt->setFromString($object->$name, jDateTime::LANG_DTFORMAT);
                    $object->$name = $dt->toString(jDateTime::DB_DTFORMAT);
                }
                elseif($ctrl->datatype instanceof jDatatypeLocaleDate
                        && $type == 'date') {
                    $dt = new jDateTime();
                    $dt->setFromString($object->$name, jDateTime::LANG_DFORMAT);
                    $object->$name = $dt->toString(jDateTime::DB_DFORMAT);
                }
            }
        }
    }

    /**
     * set form data from a DAO
     * @param string $daoSelector the selector of a dao file
     * @param string $key the primary key for the dao. if null, takes the form ID as primary key
     * @param string $dbProfile the jDb profile to use with the dao
     * @see jDao
     * @return jDaoRecordBase
     */
    public function initFromDao($daoSelector, $key = null, $dbProfile=''){
        if($key === null)
            $key = $this->container->formId;
        $dao = jDao::create($daoSelector, $dbProfile);
        $daorec = $dao->get($key);
        if(!$daorec) {
            if(is_array($key))
                $key = var_export($key,true);
            throw new jExceptionForms('jelix~formserr.bad.formid.for.dao',
                                      array($daoSelector, $key, $this->sel));
        }

        $prop = $dao->getProperties();
        foreach($this->controls as $name=>$ctrl){
            if(isset($prop[$name])) {
                $ctrl->setDataFromDao($daorec->$name, $prop[$name]['datatype']);
            }
        }
        return $daorec;
    }

    /**
     * prepare a dao with values of all controls
     * @param string $daoSelector the selector of a dao file
     * @param string $key the primary key for the dao. if null, takes the form ID as primary key
     * @param string $dbProfile the jDb profile to use with the dao
     * @return mixed return three vars : $daorec, $dao, $toInsert which have to be extracted
     * @see jDao
     */
    public function prepareDaoFromControls($daoSelector, $key = null, $dbProfile=''){
        $dao = jDao::get($daoSelector, $dbProfile);

        if($key === null)
            $key = $this->container->formId;

        if($key != null && ($daorec = $dao->get($key))) {
            $toInsert= false;
        }else{
            $daorec = jDao::createRecord($daoSelector, $dbProfile);
            if($key != null)
                $daorec->setPk($key);
            $toInsert= true;
        }
        $this->prepareObjectFromControls($daorec, $dao->getProperties());
        return compact("daorec", "dao", "toInsert");
    }

    /**
     * save data using a dao.
     * it call insert or update depending the value of the formId stored in the container
     * @param string $daoSelector the selector of a dao file
     * @param string $key the primary key for the dao. if null, takes the form ID as primary key
     * @param string $dbProfile the jDb profile to use with the dao
     * @return mixed  the primary key of the new record in a case of inserting
     * @see jDao
     */
    public function saveToDao($daoSelector, $key = null, $dbProfile=''){
        extract($this->prepareDaoFromControls($daoSelector,$key,$dbProfile));
        if($toInsert){
            // todo : what about updating the formId with the Pk ?
            $dao->insert($daorec);
        }else{
            $dao->update($daorec);
        }
        return $daorec->getPk();
    }

    /**
     * set data from a DAO, in a control
     *
     * The control must be a container like checkboxes or listbox with multiple attribute.
     * The form should contain a formId
     *
     * The Dao should map to an "association table" : its primary key should be composed by
     * the primary key stored in the formId (or the given primarykey) + the field which will contain one of
     * the values of the control. If this order is not the same as defined into the dao,
     * you should provide the list of property names which corresponds to the primary key
     * in this order : properties for the formId, followed by the property which contains
     * the value.
     * @param string $name  the name of the control
     * @param string $daoSelector the selector of a dao file
     * @param mixed  $primaryKey the primary key if the form have no id. (optional)
     * @param mixed  $primaryKeyNames list of field corresponding to primary keys (optional)
     * @param string $dbProfile the jDb profile to use with the dao
     * @see jDao
     */
    public function initControlFromDao($name, $daoSelector, $primaryKey = null, $primaryKeyNames=null, $dbProfile=''){

        if(!$this->controls[$name]->isContainer()){
            throw new jExceptionForms('jelix~formserr.control.not.container', array($name, $this->sel));
        }

        if(!$this->container->formId)
            throw new jExceptionForms('jelix~formserr.formid.undefined.for.dao', array($name, $this->sel));

        if($primaryKey === null)
            $primaryKey = $this->container->formId;

        if(!is_array($primaryKey))
            $primaryKey =array($primaryKey);

        $dao = jDao::create($daoSelector, $dbProfile);

        $conditions = jDao::createConditions();
        if($primaryKeyNames)
            $pkNamelist = $primaryKeyNames;
        else
            $pkNamelist = $dao->getPrimaryKeyNames();

        foreach($primaryKey as $k=>$pk){
            $conditions->addCondition ($pkNamelist[$k], '=', $pk);
        }

        $results = $dao->findBy($conditions);
        $valuefield = $pkNamelist[$k+1];
        $val = array();
        foreach($results as $res){
            $val[]=$res->$valuefield;
        }
        $this->controls[$name]->setData($val);
    }


    /**
     * save data of a control using a dao.
     *
     * The control must be a container like checkboxes or listbox with multiple attribute.
     * If the form contain a new record (no formId), you should call saveToDao before
     * in order to get a new id (the primary key of the new record), or you should get a new id
     * by an other way. then you must pass this primary key in the third argument.
     * If the form has already a formId, then it will be used as a primary key, unless
     * you give one in the third argument.
     *
     * The Dao should map to an "association table" : its primary key should be
     * the primary key stored in the formId + the field which will contain one of
     * the values of the control. If this order is not the same as defined into the dao,
     * you should provide the list of property names which corresponds to the primary key
     * in this order : properties for the formId, followed by the property which contains
     * the value.
     * All existing records which have the formid in their keys are deleted
     * before to insert new values.
     *
     * @param string $controlName  the name of the control
     * @param string $daoSelector the selector of a dao file
     * @param mixed  $primaryKey the primary key if the form have no id. (optional)
     * @param mixed  $primaryKeyNames list of field corresponding to primary keys (optional)
     * @param string $dbProfile the jDb profile to use with the dao
     * @see jDao
     */
    public function saveControlToDao($controlName, $daoSelector, $primaryKey = null, $primaryKeyNames=null, $dbProfile=''){

        if(!$this->controls[$controlName]->isContainer()){
            throw new jExceptionForms('jelix~formserr.control.not.container', array($controlName, $this->sel));
        }

        $values = $this->container->data[$controlName];
        if(!is_array($values) && $values != '')
            throw new jExceptionForms('jelix~formserr.value.not.array', array($controlName, $this->sel));

        if(!$this->container->formId && !$primaryKey)
            throw new jExceptionForms('jelix~formserr.formid.undefined.for.dao', array($controlName, $this->sel));

        if($primaryKey === null)
            $primaryKey = $this->container->formId;

        if(!is_array($primaryKey))
            $primaryKey =array($primaryKey);

        $dao = jDao::create($daoSelector, $dbProfile);
        $daorec = jDao::createRecord($daoSelector, $dbProfile);

        $conditions = jDao::createConditions();
        if($primaryKeyNames)
            $pkNamelist = $primaryKeyNames;
        else
            $pkNamelist = $dao->getPrimaryKeyNames();

        foreach($primaryKey as $k=>$pk){
            $conditions->addCondition ($pkNamelist[$k], '=', $pk);
            $daorec->{$pkNamelist[$k]} = $pk;
        }

        $dao->deleteBy($conditions);
        if (is_array($values)) {
            $valuefield = $pkNamelist[$k+1];
            foreach($values as $value){
                $daorec->$valuefield = $value;
                $dao->insert($daorec);
            }
        }
    }

    /**
     * return list of errors found during the check
     * @return array
     * @see jFormsBase::check
     */
    public function getErrors(){  return $this->container->errors;  }

    /**
     * set an error message on a specific field
     * @param string $field the field name
     * @param string $mesg  the error message string
     */
    public function setErrorOn($field, $mesg){
        $this->container->errors[$field]=$mesg;
    }

    /**
     *
     * @param string $name the name of the control/data
     * @param string $value the data value
     */
    public function setData($name, $value) {
        $this->controls[$name]->setData($value);
    }

    /**
     *
     * @param string $name the name of the  control/data
     * @return string the data value
     */
    public function getData($name) {
        if(isset($this->container->data[$name]))
            return $this->container->data[$name];
        else return null;
    }

    /**
     * @return array form data
     */
    public function getAllData(){ return $this->container->data; }

    /**
     * deactivate (or reactivate) a control
     * When a control is deactivated, it is not displayes anymore in the output form
     * @param string $name  the name of the control
     * @param boolean $deactivation   TRUE to deactivate, or FALSE to reactivate
     */
    public function deactivate($name, $deactivation=true) {
        $this->controls[$name]->deactivate($deactivation);
    }

    /**
    * check if a control is activated
    * @param $name the control name
    * @return boolean true if it is activated
    */
    public function isActivated($name) {
        return $this->container->isActivated($name);
    }


    /**
     * set a control readonly or not
     * @param boolean $r true if you want read only
     */
    public function setReadOnly($name, $r = true) {
        $this->controls[$name]->setReadOnly($r);
    }

    /**
     * check if a control is readonly
     * @return boolean true if it is readonly
     */
    public function isReadOnly($name) {
        return $this->container->isReadOnly($name);
    }

    /**
     * @return jFormsDataContainer
     */
    public function getContainer(){ return $this->container; }


    /**
     * @return array of jFormsControl objects
     */
    public function getRootControls(){ return $this->rootControls; }

    /**
     * @return array of jFormsControl objects
     */
    public function getControls(){ return $this->controls; }

    /**
     * @param string $name the control name you want to get
     * @return jFormsControl
     * @since jelix 1.0
     */
    public function getControl($name) {
        if(isset($this->controls[$name]))
            return $this->controls[$name];
        else return null;
    }

    /**
     * @return array of jFormsControl objects
     */
    public function getSubmits(){ return $this->submits; }

     /**
     * @return array of jFormsControl objects
     * @since 1.1
     */
    public function getHiddens(){ return $this->hiddens; }

     /**
     * @return array of jFormsControl objects
     * @since 1.1
     */
    public function getHtmlEditors(){ return $this->htmleditors; }

     /**
     * @return array of jFormsControl objects
     * @since 1.2
     */
    public function getWikiEditors(){ return $this->wikieditors; }

    /**
     * @return array of jFormsControl objects
     * @since 1.2
     */
    public function getUploads(){ return $this->uploads; }

    /**
     * call this method after initilization of the form, in order to track
     * modified controls
     * @since 1.1
     */
    public function initModifiedControlsList(){
        $this->container->originalData = $this->container->data;
    }

    /**
     * DEPRECATED. use initModifiedControlsList() instead.
     * @since 1.1b1
     * @deprecated 1.1rc1
     */
    public function resetModifiedControlsList(){
        $this->initModifiedControlsList();
    }

    /**
     * returns the old values of the controls which have been modified since
     * the call of the method initModifiedControlsList()
     * @return array key=control id,  value=old value
     * @since 1.1
     */
    public function getModifiedControls(){
        if (count($this->container->originalData)) {

            // we musn't use array_diff_assoc because it convert array values
            // to "Array" before comparison, so these values are always equal for it.
            // We shouldn't use array_udiff_assoc  because it crashes PHP, at least on
            // some PHP version.
            // so we have to compare by ourself.

            $result = array();
            $orig = & $this->container->originalData;
            foreach($this->container->data as $k=>$v1) {

                if (!isset($orig[$k])) {
                    continue;
                }

                if($this->_diffValues($orig[$k], $v1))  {
                    $result[$k] = $orig[$k];
                    continue;
                }
            }
            return $result;
        }
        else
            return $this->container->data;
    }

    protected function _diffValues(&$v1, &$v2) {
        if (is_array($v1) && is_array($v2)) {
            return (count(array_diff($v1,$v2)) > 0);
        }
        elseif (is_array($v1) || is_array($v2)) {
            return true;
        }
        else {
            return !($v1==$v2);
        }
    }

    /**
     * @return array of jFormsControl objects
     */
    public function getReset(){ return $this->reset; }

    /**
     * @return string the formId
     */
    public function id(){ return $this->container->formId; }

    /**
     * @return boolean
     */
    public function hasUpload() { return count($this->uploads)>0; }

    /**
     * @param string $buildertype  the type name of a form builder
     * @return jFormsBuilderBase
     */
    public function getBuilder($buildertype){
        global $gJConfig;
        if($buildertype == '') $buildertype = 'html';
        if(isset($gJConfig->_pluginsPathList_jforms[$buildertype])){
            if(isset($this->builders[$buildertype]))
                return $this->builders[$buildertype];
            include_once(JELIX_LIB_PATH.'forms/jFormsBuilderBase.class.php');
            include_once ($gJConfig->_pluginsPathList_jforms[$buildertype].$buildertype.'.jformsbuilder.php');
            $c = $buildertype.'JformsBuilder';
            $o = $this->builders[$buildertype] = new $c($this);
            return $o;
        }else{
            throw new jExceptionForms('jelix~formserr.invalid.form.builder', array($buildertype, $this->sel));
        }
    }

    /**
     * save an uploaded file in the given directory. the given control must be
     * an upload control of course.
     * @param string $controlName the name of the upload control
     * @param string $path path of the directory where to store the file. If it is not given,
     *                     it will be stored under the var/uploads/_modulename~formname_/ directory
     * @param string $alternateName a new name for the file. If it is not given, the file
     *                              while be stored with the original name
     * @return boolean true if the file has been saved correctly
     */
    public function saveFile($controlName, $path='', $alternateName='') {
        if ($path == '') {
            $path = JELIX_APP_VAR_PATH.'uploads/'.$this->sel.'/';
        } else if (substr($path, -1, 1) != '/') {
            $path.='/';
        }

        if(!isset($this->controls[$controlName]) || $this->controls[$controlName]->type != 'upload')
            throw new jExceptionForms('jelix~formserr.invalid.upload.control.name', array($controlName, $this->sel));

        if(!isset($_FILES[$controlName]) || $_FILES[$controlName]['error']!= UPLOAD_ERR_OK)
            return false;

        if($this->controls[$controlName]->maxsize && $_FILES[$controlName]['size'] > $this->controls[$controlName]->maxsize){
            return false;
        }
        jFile::createDir($path);
        if ($alternateName == '') {
            $path.= $_FILES[$controlName]['name'];
        } else {
            $path.= $alternateName;
        }
        return move_uploaded_file($_FILES[$controlName]['tmp_name'], $path);
    }

    /**
     * save all uploaded file in the given directory
     * @param string $path path of the directory where to store the file. If it is not given,
     *                     it will be stored under the var/uploads/_modulename~formname_/ directory
     */
    public function saveAllFiles($path='') {
        if ($path == '') {
            $path = JELIX_APP_VAR_PATH.'uploads/'.$this->sel.'/';
        } else if (substr($path, -1, 1) != '/') {
            $path.='/';
        }

        if(count($this->uploads))
            jFile::createDir($path);

        foreach($this->uploads as $ref=>$ctrl){

            if(!isset($_FILES[$ref]) || $_FILES[$ref]['error']!= UPLOAD_ERR_OK)
                continue;
            if($ctrl->maxsize && $_FILES[$ref]['size'] > $ctrl->maxsize)
                continue;

            move_uploaded_file($_FILES[$ref]['tmp_name'], $path.$_FILES[$ref]['name']);
        }
    }

    /**
    * add a control to the form
    * @param jFormsControl $control the control to add
    */
    public function addControl($control){
        $this->rootControls [$control->ref] = $control;
        $this->addChildControl($control);

        if($control instanceof jFormsControlGroups) {
            foreach($control->getChildControls() as $ctrl)
                $this->addChildControl($ctrl);
        }
    }

    /**
     * add a control to the form, before the specified control
     * @param jFormsControl $control the control to add
     * @param string $ref The ref of the control the new control should be inserted before
     * @since 1.1
     */
    public function addControlBefore($control, $ref){
        if(isset($this->rootControls[$ref])){
            $controls = array();
            foreach($this->rootControls as $k=>$c){
                if($k == $ref)
                    $controls[$control->ref] = null;
                $controls[$k] = $c;
            }
            $this->rootControls = $controls;
        }
        $this->addControl($control);
    }


    function removeControl($name) {
        if(!isset($this->rootControls [$name]))
            return;
        unset($this->rootControls [$name]);
        unset($this->controls [$name]);
        unset($this->submits [$name]);
        if($this->reset && $this->reset->ref == $name)
            $this->reset = null;
        unset($this->uploads [$name]);
        unset($this->hiddens [$name]);
        unset($this->htmleditors [$name]);
        unset($this->wikieditors [$name]);
        unset($this->container->data[$name]);
    }


    /**
    * declare a child control to the form. The given control should be a child of an other control
    * @param jFormsControl $control
    */
    public function addChildControl($control){
        $this->controls [$control->ref] = $control;
        switch ($control->type) {
            case 'submit':
                $this->submits [$control->ref] = $control;
                break;
            case 'reset':
                $this->reset = $control;
                break;
            case 'upload':
                $this->uploads [$control->ref] = $control;
                break;
            case 'hidden':
                $this->hiddens [$control->ref] = $control;
                break;
            case 'htmleditor':
                $this->htmleditors [$control->ref] = $control;
                break;
            case 'wikieditor':
                $this->wikieditors [$control->ref] = $control;
                break;
        }
        $control->setForm($this);

        if(!isset($this->container->data[$control->ref])){
            if ( $control->datatype instanceof jDatatypeDateTime && $control->defaultValue == 'now') {
                $dt = new jDateTime();
                $dt->now();
                $this->container->data[$control->ref] = $dt->toString($control->datatype->getFormat());
            }
            else {
                $this->container->data[$control->ref] = $control->defaultValue;
            }
        }
    }

    /**
     * generate a new token for security against CSRF
     * a builder should call it and create for example an hidden input
     * so jForms could verify it after the submit.
     * @return string the token
     * @since 1.1.2
     */
    public function createNewToken() {
      if ($this->container->formId != jForms::DEFAULT_ID || $this->container->token == '') {
          $tok = md5($this->container->formId.time().session_id());
          return ($this->container->token = $tok);
      }
      return $this->container->token;
    }
}