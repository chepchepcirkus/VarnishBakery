<?php

namespace VarnishBakery\Model;

use Cake\Core\Plugin;
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use VarnishBakery\Model\Config as Config;

class Listener implements EventListenerInterface
{
    /**
     * @return array
     */
    public function implementedEvents()
    {
        return [
            'Controller.beforeRender' => 'setHeaderCacheFlag',
        ];
    }

    /**
     * @param Event $event cakephp event
     * @return null
     */
    public function setHeaderCacheFlag(Event $event)
    {
        $config = new Config();
        $noCacheRoutes = $config->getConfig('no_cache_routes');
        $controller = $event->getSubject();
        $found = false;

        $path = explode('/', $controller->request->url);
        $pattern = '/^' . $path[0] . '/';
        $grep = preg_grep($pattern, $noCacheRoutes);

        // Check if url match against restriction url from configuration
        if (count($grep) > 0) {
            $c = count($path);
            for ($i = 1; $i <= $c; $i++) {
                foreach ($grep as $data) {
                    if ($data === $controller->request->url) {
                        $found = true;
                        break;
                    }
                    $customPath = '';
                    if ($i === 0) {
                        $customPath = $path[0];
                    } else {
                        for ($j = 0; $j < $i; $j++) {
                            if (strlen($customPath) > 0) {
                                $customPath .= '/';
                            }
                            $customPath .= $path[$j];
                        }
                    }

                    if ($customPath . '/*' === $data) {
                        $found = true;
                        break;
                    }
                }
                if ($found) {
                    break;
                }
            }
        }

        if ($found) {
            $controller->response = $controller->response->withHeader('X-VarnishBakery-Cache', "0");
            $event->setResult($controller);
        }
    }

    /**
     * @param string $request request
     * @param string $index index
     * @return string
     */
    public function buildRoute($request, $index)
    {
        $route = '';
        foreach ($index as $id) {
            $param = $request->getParam($id);
            if (!is_null($param) || !$param) {
                if (strlen($route) > 0) {
                    $route .= '/';
                }
                $route .= strtolower($param);
            }
        }

        return $route;
    }
}
