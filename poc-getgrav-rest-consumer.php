<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Uri;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\GPM\Response;

class PocGetgravRestConsumerPlugin extends Plugin
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
        $route = $this->config->get('plugins.poc-getgrav-rest-consumer.route_issue');

        if ($route && strpos($uri->path(), $route) !== FALSE) {
            $this->enable([
                'onPageInitialized' => ['onPageInitialized', 0]
            ]);
        }

        $this->enable([
            'onPagesInitialized' => ['addIssuePage', 0],
        ]);
    }

    /**
     * Add Wiki page
     */
    public function addIssuePage()
    {
        $uri = $this->grav['uri'];
        $route = $this->config->get('plugins.poc-getgrav-rest-consumer.route_issue');
        $nid = substr($uri->url(), strlen($route) + 1);

        // Move fetch in here so we're sure we have data?

        /** @var Pages $pages */
        $pages = $this->grav['pages'];
        $page = $pages->dispatch($uri->route());

        if (!$page) {
          $page = new Page;

          if (empty($nid)) {
            $page->init(new \SplFileInfo(__DIR__ . "/pages/issuelist.md"));
          }
          else {
            if ($this->validNid($nid)) {
              $page->init(new \SplFileInfo(__DIR__ . "/pages/issue.md"));
            }
          }
          $page->slug(basename($uri->route()));

          $pages->addPage($page, $uri->route());
        }
    }

    /**
     * Display poc-getgrav-rest-consumer page.
     */
    public function onPageInitialized()
    {
      /** @var Uri $uri */
      $uri = $this->grav['uri'];
      $route = $this->config->get('plugins.poc-getgrav-rest-consumer.route_issue');

      $nid = substr($uri->url(), strlen($route) + 1);

      // Add caching, or is it handled by Grav?

      if (empty($nid)) {
        $page = $this->grav['page'];
        $page->title('Issues');
        $page->modifyHeader('title', 'Issues');

        $status_filter = null;
        if (isset($_REQUEST['status']) && is_numeric($_REQUEST['status'])) {
          $status_filter = $_REQUEST['status'];
        }

        $twig = $this->grav['twig'];
        $twig->twig_vars['issues'] = $this->getIssueList($status_filter);
        $twig->twig_vars['issue_base_url'] = $route;
        $twig->twig_vars['status_list'] = $this->getActiveStatusList();
        $twig->twig_vars['status_filter_active'] = $status_filter;
      }
      else {
        $content = $this->getIssue($nid);

        $page = $this->grav['page'];
        if (!empty($content['body']['value'])) {
          $page->content($content['body']['value']);
        }
        $page->title($content['title']);
        $page->modifyHeader('title', $content['title']);

        $twig = $this->grav['twig'];
        $twig->twig_vars['component'] = $content['field_issue_component'];
        $twig->twig_vars['status'] = $this->getStatusLabel($content['field_issue_status']);
        $twig->twig_vars['nid'] = $nid;
        $twig->twig_vars['issue_base_url'] = $route;
      }
    }

    private function getStatusLabel($id) {
      $statusLabels = $this->config->get('plugins.poc-getgrav-rest-consumer.status');
      return $statusLabels[$id];
    }

    private function getActiveStatusList() {
      $issues = $this->getIssueList();
      $status = array_column($issues, 'status');
      $status = array_unique($status);
      $status = array_flip($status);

      $statusLabels = $this->config->get('plugins.poc-getgrav-rest-consumer.status');
      return array_intersect_key($statusLabels, $status);
    }

    private function validNid($nid) {
      $issues = $this->getIssueList();
      return array_key_exists($nid, $issues);
    }

    private function getIssue($nid) {
      /** @var Cache $cache */
      $cache = $this->grav['cache'];

      $cache_ttl = $this->config->get('plugins.poc-getgrav-rest-consumer.cache_ttl');

      /** @var Debugger $debugger */
      $debugger = $this->grav['debugger'];

      $url ='https://www.drupal.org/api-d7/node/' . $nid . '.json';
      $cache_id = md5('poc-getgrav-rest-consumer' . $url);

      $content = $cache->fetch($cache_id);
      if ($content === FALSE) {
        $debugger->addMessage("Fetching issue data.");
        $result = Response::get($url);
        $content = json_decode($result, true);
        $cache->save($cache_id, $content, $cache_ttl);
      }
      return $content;
    }

    private function getIssueList($status = null) {
      /** @var Cache $cache */
      $cache = $this->grav['cache'];

      /** @var Debugger $debugger */
      $debugger = $this->grav['debugger'];

      $project_nid = $this->config->get('plugins.poc-getgrav-rest-consumer.project_nid');
      $cache_ttl = $this->config->get('plugins.poc-getgrav-rest-consumer.cache_ttl');

      $url ='https://www.drupal.org/api-d7/node.json?type=project_issue&field_project=' . $project_nid;
      $cache_id = md5('poc-getgrav-rest-consumer' . $url);

      $issues = $cache->fetch($cache_id);
      if ($issues === FALSE) {
        $debugger->addMessage("Fetching issue list.");
        $result = Response::get($url);
        $content = json_decode($result, true);
        $issues = [];
        foreach ($content['list'] as $issue) {
          $issues[$issue['nid']] = [
            'nid' => $issue['nid'],
            'title' => $issue['title'],
            'status' => $issue['field_issue_status'],
          ];
        }
        $cache->save($cache_id, $issues, $cache_ttl);
      }

      // Filter on status.
      if ($status) {
        $issues = array_filter($issues, function ($val) use ($status) {
          return $val['status'] == $status;
        });
      }
      return $issues;
    }
}
