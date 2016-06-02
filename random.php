<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\GPM\Response;

class RandomPlugin extends Plugin
{
    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onTwigTemplatePaths'  => ['onTwigTemplatePaths', 0],
        ];
    }

    /**
     * Add twig paths to plugin templates.
     */
    public function onTwigTemplatePaths()
    {
        $twig = $this->grav['twig'];
        $twig->twig_paths[] = __DIR__ . '/templates';
    }

    /**
     * Activate plugin if path matches to the configured one.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            $this->active = false;
            return;
        }

        /** @var Uri $uri */
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.random.route');

        if ($route && strpos($uri->path(), $route) !== FALSE) {
            $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0]
            ]);
        }

        $this->enable([
            'onPagesInitialized' => ['addWikiPage', 0],
        ]);
    }

    /**
     * Add Wiki page
     */
    public function addWikiPage()
    {
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.random.route');
        $nid = substr($uri->url(), strlen($route) + 1);

        // Move fetch in here so we're sure we have data?

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $page = $pages->dispatch($uri->route());

        if (!$page) {
          $page = new Page;

          if (empty($nid)) {
            $page->init(new \SplFileInfo(__DIR__ . "/pages/wikilist.md"));
          }
          else {
            $page->init(new \SplFileInfo(__DIR__ . "/pages/wiki.md"));
          }
          $page->slug(basename($uri->route()));

          $pages->addPage($page, $uri->route());
        }
    }

    /**
     * Display random page.
     */
    public function onPageInitialized()
    {
      /** @var Uri $uri */
      $uri = $this->grav['uri'];
      $route = $this->config->get('plugins.random.route');

      $nid = substr($uri->url(), strlen($route) + 1);

      // Add caching, or is it handled by Grav?

      if (empty($nid)) {
        $url ='https://www.drupal.org/api-d7/node.json?type=project_issue&field_project=2721905';
        $result = Response::get($url);
        $content = json_decode($result, true);

        $page = $this->grav['page'];
        $page->title('Issues');
        $page->modifyHeader('title', 'Issues');

        $issues = [];
        foreach ($content['list'] as $issue) {
          $issues[] = [
            'nid' => $issue['nid'],
            'title' => $issue['title'],
          ];
        }
        $twig = $this->grav['twig'];
        $twig->twig_vars['issues'] = $issues;
      }
      else {
        $url ='https://www.drupal.org/api-d7/node/' . $nid . '.json';
        $result = Response::get($url);
        $content = json_decode($result, true);

        $page = $this->grav['page'];
        if (!empty($content['body']['value'])) {
          $page->content($content['body']['value']);
        }
        $page->title($content['title']);
        $page->modifyHeader('title', $content['title']);

        $twig = $this->grav['twig'];
        $twig->twig_vars['component'] = $content['field_issue_component'];
        $twig->twig_vars['nid'] = $nid;
      }
    }
}
