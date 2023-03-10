<?php
/**
 * @file
 * Implementation of the `yii\mustache\Loader` class.
 */
namespace yii\mustache;

// Dependencies.
use yii\base\{InvalidCallException, InvalidParamException, Object};
use yii\helpers\FileHelper;

/**
 * Loads views from the file system.
 */
class Loader extends Object implements \Mustache_Loader {

  /**
   * Initializes a new instance of the class.
   * @param $renderer The instance used to render the views.
   */
  public function __construct(ViewRenderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * @var string CACHE_KEY_PREFIX
   * The string prefixed to every cache key in order to avoid name collisions.
   */
  const CACHE_KEY_PREFIX = __CLASS__;

  /**
   * @var string DEFAULT_EXTENSION
   * The default extension of template files.
   */
  const DEFAULT_EXTENSION = 'mustache';

  /**
   * @var yii::mustache::ViewRenderer $renderer
   * The instance used to render the views.
   */
  private $renderer;

  /**
   * @var array $views
   * The loaded views.
   */
  private $views = [];

  /**
   * Loads the view with the specified name.
   * @param $name The view name.
   * @return The view contents.
   * @throws yii::base::InvalidCallException Unable to locate the view file.
   */
  public function load($name): string {
    if(!isset($this->views[$name])) {
      $cache = ($this->renderer->cacheId ? \Yii::$app->get($this->renderer->cacheId) : null);
      $key = static::CACHE_KEY_PREFIX.$name;

      if($cache && $cache->exists($key)) $output = $cache[$key];
      else {
        $path = FileHelper::localize($this->findViewFile($name));
        if(!is_file($path)) throw new InvalidCallException(sprintf('The view file "%s" does not exist.', $path));

        $output = @file_get_contents($path);
        if($cache) $cache->set($key, $output, $this->renderer->cachingDuration);
      }

      $this->views[$name] = $output;
    }

    return $this->views[$name];
  }

  /**
   * Finds the view file based on the given view name.
   * @param $name The view name.
   * @return The view file path.
   * @throws yii::base::InvalidCallException Unable to locate the view file.
   * @throws yii::base::InvalidParamException The view name is empty.
   */
  protected function findViewFile(string $name): string {
    if(!mb_strlen($name)) throw new InvalidParamException('The view name is empty.');
    $controller = \Yii::$app->controller;

    if(mb_substr($name, 0, 2) == '//') $file = \Yii::$app->viewPath.'/'.ltrim($name, '/');
    else if($name[0] == '/') {
      if(!$controller) throw new InvalidCallException(sprintf('Unable to locale the view "%s": no active controller.', $name));
      $file = $controller->module->viewPath.'/'.ltrim($name, '/');
    }
    else {
      $viewPath = ($controller ? $controller->viewPath : \Yii::$app->viewPath);
      $file = \Yii::getAlias("$viewPath/$name");
    }

    $view = \Yii::$app->view;
    if($view && $view->theme) $file = $view->theme->applyTo($file);
    if(!mb_strlen(pathinfo($file, PATHINFO_EXTENSION))) $file .= '.'.($view ? $view->defaultExtension : static::DEFAULT_EXTENSION);
    return $file;
  }
}
