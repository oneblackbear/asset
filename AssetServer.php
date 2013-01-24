<?php
namespace Wax\Asset;
use Assetic\AssetManager;
use Assetic\Asset\AssetCollection;
use Assetic\Asset\FileAsset;
use Symfony\Component\HttpFoundation\Response;


/**
* A collection of assets loaded by glob.
*
* @author Ross Riley
*/
class AssetServer {
  
  public $asset_manager = false;
  public $listeners     = array();
  
  public $mime_types_map = array(
        'txt' => 'text/plain',
        'htm' => 'text/html',
        'html' => 'text/html',
        'php' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'xml' => 'application/xml',
        'swf' => 'application/x-shockwave-flash',
        'flv' => 'video/x-flv',

        // images
        'png' => 'image/png',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'ico' => 'image/vnd.microsoft.icon',
        'tiff' => 'image/tiff',
        'tif' => 'image/tiff',
        'svg' => 'image/svg+xml',
        'svgz' => 'image/svg+xml',

        // archives
        'zip' => 'application/zip',
        'rar' => 'application/x-rar-compressed',
        'exe' => 'application/x-msdownload',
        'msi' => 'application/x-msdownload',
        'cab' => 'application/vnd.ms-cab-compressed',

        // audio/video
        'mp3' => 'audio/mpeg',
        'qt' => 'video/quicktime',
        'mov' => 'video/quicktime',

        // adobe
        'pdf' => 'application/pdf',
        'psd' => 'image/vnd.adobe.photoshop',
        'ai' => 'application/postscript',
        'eps' => 'application/postscript',
        'ps' => 'application/postscript',

        // ms office
        'doc' => 'application/msword',
        'rtf' => 'application/rtf',
        'xls' => 'application/vnd.ms-excel',
        'ppt' => 'application/vnd.ms-powerpoint',

        // open office
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
  );
  
  
  public function __construct() {
    $this->asset_manager = new AssetManager;
  }
  
  /**
   * Registers an asset handler, the listener decides what url fragments to take responsibility for.
   *
   * @param string $listener 
   * @param string $asset_directory 
   * @param string $pattern 
   */
   public function register($listener, $asset_directory, $pattern) {
     if(!$pattern) $pattern = "/*";
     $glob = rtrim($asset_directory,"/").$pattern;
     $finder = new RecursiveAssetFinder($glob);
     $bundle = $this->bundle_formatter($listener);
     $this->listeners[$listener]=$bundle;
     $this->asset_manager->set($bundle, $finder->get_collection());
   }

  
  /**
   * Returns whether server can handle url based on listener
   *
   * @param string $listener 
   * @return boolean
   */
  public function handles($url) {
    foreach($this->listeners as $pattern=>$bundle) {
      if(preg_match("#^".preg_quote($pattern)."#", $url)) return $this->asset_manager->has($bundle);
    }
    return false;
  }

  
  
  public function serve($url) {   
    foreach($this->listeners as $pattern=>$bundle) {
      if(preg_match("#^".preg_quote($pattern)."#", $url)) {
        $matched_pattern = $pattern;
        if(!$this->asset_manager->has($bundle)) return;
        $collection = $this->asset_manager->get($bundle);
      }
    }
    $asset_url = preg_replace("#^".$matched_pattern."#", "", $url);
    foreach($collection as $asset) {
      if($asset->relative == $asset_url) {
        $response = new Response(
            $asset->dump(),
            200,
            array('content-type' => $this->guess_mime($asset))
        );
        $response->send();
      }
    }
  }
  
  private function guess_mime($asset) {
    $path = pathinfo($asset->relative);
    $mapped_mime = $this->mime_types_map[$path["extension"]];
    if($mapped_mime) return $mapped_mime;
    else {
      $finfo = new \finfo(FILEINFO_MIME_TYPE);
      return $finfo->buffer($asset->dump());
    }
  }
  
  public function bundle_builder($name, $options = array(), $plugin="", $type) {
    $tag_build = new \AssetTagHelper;
    if(ENV=="development") {     
      if($plugin) {
        $as = \AutoLoader::get_asset_server();
        if($as->handles($type."/".$name)) {
          $asset_bundle = $type."_".$name;
          if($as->asset_manager->has($asset_bundle)) {
            $base = dirname(dirname($as->asset_manager->get($asset_bundle)->getSourceRoot()))."/";
            $d = $as->asset_manager->get($asset_bundle)->getSourceRoot();
          }
        } else {
          $base = PLUGIN_DIR.$plugin."/resources/public/";
          $d = $base.$type."/";
        } 
      } else {
        $base = PUBLIC_DIR;
        $d = $base.$type."/".$name; 
      }
    
      if(!is_readable($d)) return false;
         
      if($type == "stylesheets") {
        $filter ="css";
        $b_method = "stylesheet_link_tag";
      }
      if($type == "javascripts") {
        $filter ="js";
        $b_method = "javascript_include_tag";
      }
      
      foreach($tag_build->iterate_dir($d, $filter) as $file){
        $name = $file->getPathName();
        
        
        $ret .= $tag_build->$b_method("/".str_replace($base, "", $name), $options);
      }  
    
    
    } else $ret = $tag_build->$b_method($type."/build/{$name}_combined", $options);
    return $ret;
  }


  
  private function bundle_formatter($listener) {
    return preg_replace("/[^A-Za-z0-9 ]/", '_', $listener);
  }
  
  
}
