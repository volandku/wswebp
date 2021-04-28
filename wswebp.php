<?php
defined('_JEXEC') or die;
include_once __DIR__."/vendor/autoload.php";
use DOMUtilForWebP\ImageUrlReplacer;



class PlgContentWswebp extends JPlugin
{
	protected $app;
	public function __construct(& $subject, $config)
	{
		parent::__construct($subject, $config);
		if (!isset($this->app))
		{
			$this->app = JFactory::getApplication();
		}
	}

	protected function allowConfigWebp()
    {
        $app = JFactory::getApplication();
        $params = JComponentHelper::getParams('com_media');
        $ue = $params->get('upload_extensions');
        $image_extensions=$params->get('image_extensions');
        $upload_mime=$params->get('upload_mime');
        $corrected=false;
        $close=JText::_('PLG_WSWEBP_CLOSE_TEXT');

        if (mb_strpos($ue,'webp')===FALSE) { $ue.=',webp'; $corrected=true;}
        if (mb_strpos($ue,'WEBP')===FALSE) { $ue.=',WEBP'; $corrected=true;}
        if (mb_strpos($upload_mime,'image/webp')===FALSE) { $upload_mime.=',image/webp'; $corrected=true;}
        if (mb_strpos($image_extensions,'webp')===FALSE) { $image_extensions.=',webp'; $corrected=true;}
        if ($corrected)
        {
            $params->set('upload_extensions',$ue);
            $params->set('upload_mime',$upload_mime);
            $params->set('image_extensions',$image_extensions);

            // Save the parameters
            $componentid = JComponentHelper::getComponent('com_media')->id;
            $table = JTable::getInstance('extension');
            $table->load($componentid);
            $table->bind(array('params' => $params->toString()));

            // check for error
            if (!$table->check()) {
                echo $table->getError();
                return false;
                }
            // Save to database
            if (!$table->store()) {
                echo $table->getError();
                return false;
                }
        }
        if (!$corrected) return JText::_('PLG_WSWEBP_WAS_DONE_TEXT').$close; else return JText::_('PLG_WSWEBP_DONE_TEXT').$close;
    }

    public function onAjaxWswebp()
    {
        $lang = JFactory::getLanguage();
        $lang->load('plg_content_wswebp.sys', JPATH_ADMINISTRATOR);

        return self::allowConfigWebp();
    }

	public function __onAfterRender()
	{
		if ($this->app->isClient('administrator') )
		{
			return;
		}

        $body=JFactory::getApplication()->getBody();
        // sp-main-body
        $time=microtime(true);
        $modifiedBody = ImageUrlReplacer::replace($body);

        JFactory::getApplication()->setBody((microtime(true)-$time).$modifiedBody);

		return;


	}
    public function __onContentAfterDisplay($context, $item, $params, $limitstart = 0)
    {
       var_dump(123);
       var_dump($context);
    }
    public function __onContentBeforeDisplay($context, $item, $params, $limitstart = 0)
    {
        var_dump(123);
        var_dump($context);
    }
    public static function createWebp($file,$extension,$quality)
    {

        $file1=JPATH_SITE.$file;
        if (in_array($extension,['jpeg','jpg'])) $image=imagecreatefromjpeg($file1);
        if ($extension=='png') $image=imagecreatefrompng($file1);
        if ($extension=='bmp') $image=imagecreatefrombmp($file1);
        if ($extension=='gif') $image=imagecreatefromgif($file1);
        ob_start();
        if (in_array($extension,['jpeg','jpg'])) imagejpeg($image,NULL,100);
	if ($extension=='png')
        {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image,NULL,9);
        }
        if ($extension=='bmp') imagebmp($image,NULL,100);
        if ($extension=='gif') imagegif($image,NULL,100);
        $cont= ob_get_contents();
        ob_end_clean();
        imagedestroy($image);
        $content =  imagecreatefromstring($cont);
        imagewebp($content,$file1.".webp",$quality);
        imagedestroy($content);

    }

    public function onContentPrepare($context, &$item, $params, $limitstart = 0)
    {
         // var_dump($context); // mod_custom.content com_content.article
        //$b=get_browser(null,true);
        //$b=$_SERVER['HTTP_ACCEPT'];
        // var_dump($b);


        if( strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) === false ) {
            // webp is supported!
            return;
        }

        $allow_modules=$this->params->get('allow_modules');
        $modules_except=$this->params->get('modules_except');
        $e_modules=$this->params->get('e_modules');
        if (!is_array($e_modules) && !empty($e_modules)) $e_modules=[$e_modules]; // Если получили одно значение, а не массив то преобразуем его в массив
        $module=explode('.',$context)[0];
        if (mb_substr($module,0,4)=="mod_")
        {
            if ($allow_modules!=1) return;
            if ($modules_except==1 && is_array($e_modules) && in_array($module,$e_modules)) return;
            if ($modules_except==0 && is_array($e_modules) && (!in_array($module,$e_modules))) return;
        }

        // var_dump($item);
        $text=&$item->text;
        if (in_array($context,['com_k2.itemlist']))
        {
            $to_replace=['imageXSmall','imageSmall','imageMedium',
                'imageLarge','imageXLarge','imageGeneric'];

            foreach ($to_replace as $k=>$t)
            {
                if (!isset($item->$t)) continue;
                $ext=strtolower(pathinfo($item->$t)['extension']);
                if (in_array($ext,['jpg','jpeg','png','gif','bmp']))
                    {

                        $replacing=$item->$t;
                        $item->$t=$item->$t.'.webp';
                        if (mb_substr($replacing,0,1)!="/") $replacing="/".$replacing;
                        if (file_exists(JPATH_SITE.$replacing.".webp")) continue; // уже есть - отличненько
                        if ($this->params->get('create_webp')==1) self::createWebp($replacing,$ext,$this->params->get('webp_quality',80));

                    }

            }
            // var_dump($t);
            if ($this->params->get('debug')==1)var_dump($item->$t);
        }
//        public 'imageXSmall' => string '/media/k2/items/cache/fbe0cb468ebf47784fd8b26af4021e7e_XS.jpg' (length=61)
//  public 'imageSmall' => string '/media/k2/items/cache/fbe0cb468ebf47784fd8b26af4021e7e_S.jpg' (length=60)
//  public 'imageMedium' => string '/media/k2/items/cache/fbe0cb468ebf47784fd8b26af4021e7e_M.jpg' (length=60)
//  public 'imageLarge' => string '/media/k2/items/cache/fbe0cb468ebf47784fd8b26af4021e7e_L.jpg' (length=60)
//  public 'imageXLarge' => string '/media/k2/items/cache/fbe0cb468ebf47784fd8b26af4021e7e_XL.jpg' (length=61)
//  public 'imageGeneric'

        $text=ImageUrlReplacer::replace($text);

        if ($this->params->get('debug')==1)  var_dump(ImageUrlReplacer::$replaced);
        if ($this->params->get('create_webp')==1)
        {
            // проверяем файлики и создаем если нет
            foreach (ImageUrlReplacer::$replaced as &$replacing)
            {
                // var_dump($replacing);
                //var_dump(mb_substr($replacing,0,1));
                if (mb_substr($replacing,0,1)!="/") $replacing="/".$replacing;
                if ($this->params->get('debug')==1) var_dump($replacing);
                var_dump(file_exists(JPATH_SITE.$replacing.".webp"));
                if (file_exists(JPATH_SITE.$replacing.".webp")) continue; // уже есть - отличненько
                $ext=strtolower(pathinfo($replacing)['extension']);
                if (in_array($ext,['jpg','jpeg','png','gif','bmp'])) self::createWebp($replacing,$ext,$this->params->get('webp_quality',80));

            }
        }

    }
}
