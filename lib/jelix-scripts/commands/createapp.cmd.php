<?php

/**
* @package     jelix-scripts
* @author      Laurent Jouanneau
* @contributor Loic Mathaud
* @contributor Gildas Givaja (bug #83)
* @contributor Christophe Thiriot
* @contributor Bastien Jaillot
* @copyright   2005-2008 Laurent Jouanneau, 2006 Loic Mathaud, 2007 Gildas Givaja, 2007 Christophe Thiriot, 2008 Bastien Jaillot
* @link        http://www.jelix.org
* @licence     GNU General Public Licence see LICENCE file or http://www.gnu.org/licenses/gpl.html
*/

class createappCommand extends JelixScriptCommand {

    public  $name = 'createapp';
    public  $allowed_options=array('-nodefaultmodule'=>false,
                                   '-withcmdline'=>false,
                                   '-wwwpath'=>true);
    public  $allowed_parameters=array();

    public  $syntaxhelp = "[-nodefaultmodule] [-withcmdline] [-wwwpath a_path]";
    public  $help='';

    function __construct(){
        $this->help= array(
            'fr'=>"
    Crée une nouvelle application avec tous les répertoires nécessaires et un module
    du même nom que l'application.

    Si l'option -nodefaultmodule est présente, le module n'est pas créé.

    Si l'option -withcmdline est présente, crée un point d'entrée afin de 
    développer des scripts en ligne de commande.

    Le nom de l'application doit être indiqué
    1) soit en premier paramètre du script jelix
          ".$_SERVER['argv'][0]." --helloApp
    2) soit dans une variable d'environnement JELIX_APP_NAME.
    ",
            'en'=>"
    Create a new application with all directories and a module which have 
    the same name asthe one of the application.

    If you give -nodefaultmodule option, it won't create the module. 

    If you give the -withcmdline option, it will create an entry point for
    command line script.

    The application name should be provided by either of this two ways:
    1) by given the name as parameter. Example for a helloApp application
          ".$_SERVER['argv'][0]." --helloApp
    2) or by given the name in the environment variable: JELIX_APP_NAME.
    ",
    );
    }

    public function run(){
       if(file_exists(JELIX_APP_PATH)){
           die("Error : this application is already created\n");
       }

        $this->createDir(JELIX_APP_PATH);

        if ($p = $this->getOption('-wwwpath')) {
            $wwwpath = realpath(JELIX_APP_PATH.$p).'/';
        }
        else {
            $wwwpath = JELIX_APP_WWW_PATH;
        }

       $this->createDir(JELIX_APP_TEMP_PATH);
       $this->createDir($wwwpath);
       $this->createDir(JELIX_APP_VAR_PATH);
       $this->createDir(JELIX_APP_LOG_PATH);
       $this->createDir(JELIX_APP_CONFIG_PATH);
       $this->createDir(JELIX_APP_CONFIG_PATH.'index/');
       $this->createDir(JELIX_APP_VAR_PATH.'overloads/');
       $this->createDir(JELIX_APP_VAR_PATH.'themes/');
       $this->createDir(JELIX_APP_VAR_PATH.'themes/default/');
       $this->createDir(JELIX_APP_VAR_PATH.'uploads/');
       $this->createDir(JELIX_APP_VAR_PATH.'sessions/');
       $this->createDir(JELIX_APP_PATH.'modules');
       $this->createDir(JELIX_APP_PATH.'plugins');
       $this->createDir(JELIX_APP_PATH.'plugins/coord/');
       $this->createDir(JELIX_APP_PATH.'plugins/tpl/');
       $this->createDir(JELIX_APP_PATH.'plugins/tpl/common');
       $this->createDir(JELIX_APP_PATH.'plugins/tpl/html');
       $this->createDir(JELIX_APP_PATH.'plugins/tpl/text');
       $this->createDir(JELIX_APP_PATH.'plugins/db/');
       $this->createDir(JELIX_APP_PATH.'plugins/auth/');
       $this->createDir(JELIX_APP_PATH.'responses');

        $param = array();
        $param['default_id'] = $GLOBALS['APPNAME'].JELIXS_INFO_DEFAULT_IDSUFFIX;

        if($this->getOption('-nodefaultmodule'))
            $param['tplname'] = 'jelix~defaultmain';
        else
            $param['tplname'] = $GLOBALS['APPNAME'].'~main';

        $param['config_file'] = 'index/config.ini.php';
        $param['modulename'] = $GLOBALS['APPNAME'];
        $param['rp_temp']= jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_TEMP_PATH, true);
        $param['rp_var'] = jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_VAR_PATH,  true);
        $param['rp_log'] = jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_LOG_PATH,  true);
        $param['rp_conf']= jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_CONFIG_PATH, true);
        $param['rp_www'] = jxs_getRelativePath(JELIX_APP_PATH, $wwwpath,  true);
        $param['rp_cmd'] = jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_CMD_PATH,  true);
        $param['rp_jelix'] = jxs_getRelativePath(JELIX_APP_PATH, JELIX_LIB_PATH, true);
        $param['rp_app']   = jxs_getRelativePath($wwwpath, JELIX_APP_PATH, true);

        $this->createFile(JELIX_APP_PATH.'.htaccess','htaccess_deny',$param);
        $this->createFile(JELIX_APP_PATH.'project.xml','project.xml.tpl',$param);
        $this->createFile(JELIX_APP_CONFIG_PATH.'defaultconfig.ini.php','var/config/defaultconfig.ini.php.tpl',$param);
        $this->createFile(JELIX_APP_CONFIG_PATH.'dbprofils.ini.php','var/config/dbprofils.ini.php.tpl',$param);
        $this->createFile(JELIX_APP_CONFIG_PATH.'index/config.ini.php','var/config/index/config.ini.php.tpl',$param);

        $this->createFile(JELIX_APP_PATH.'responses/myHtmlResponse.class.php','myHtmlResponse.class.php.tpl',$param);
 

       
        $this->createFile(JELIX_APP_PATH.'application.init.php','application.init.php.tpl',$param);
    
        $this->createFile($wwwpath.'index.php','www/index.php.tpl',$param);
        $this->createFile($wwwpath.'.htaccess','htaccess_allow',$param);

        if(!$this->getOption('-nodefaultmodule')){
            $cmd = jxs_load_command('createmodule');
            $cmd->init(array('-addinstallzone'=>true),array('module'=>$GLOBALS['APPNAME']));
            $cmd->run();
            $this->createFile(JELIX_APP_PATH.'modules/'.$GLOBALS['APPNAME'].'/templates/main.tpl', 'main.tpl.tpl', $param);
        }

        if ($this->getOption('-withcmdline')) {
            $agcommand = jxs_load_command('createctrl');
            $options = array('-cmdline'=>true);
            $agcommand->init($options,array('module'=>$GLOBALS['APPNAME'], 'name'=>'default','method'=>'index'));
            $agcommand->run();

            $this->createDir(JELIX_APP_CMD_PATH);
            $this->createDir(JELIX_APP_CONFIG_PATH.'cmdline');
            $this->createFile(JELIX_APP_CONFIG_PATH.'cmdline/config.ini.php','var/config/cmdline/config.ini.php.tpl', $param);
            $param['rp_cmd'] =jxs_getRelativePath(JELIX_APP_PATH, JELIX_APP_CMD_PATH,true);
            $this->createFile(JELIX_APP_CMD_PATH.'cmdline.php','scripts/cmdline.php.tpl',$param);
        }
    }
}

