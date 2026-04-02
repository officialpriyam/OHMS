<?php
/**
 * OHMS.
 *
 * @copyright OHMS, Inc (https://www.OHMS.org)
 * @license   Apache-2.0
 *
 * Copyright OHMS, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 *
 * ---
 *
 * BoxBilling.
 *
 * @copyright BoxBilling, Inc (https://www.boxbilling.org)
 * @license   Apache-2.0
 *
 * Copyright BoxBilling, Inc
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */



namespace Priyx\Mod\News\Controller;

class Admin implements \Priyx\InjectionAwareInterface
{
    protected $di;

    /**
     * @param mixed $di
     */
    public function setDi($di)
    {
        $this->di = $di;
    }

    /**
     * @return mixed
     */
    public function getDi()
    {
        return $this->di;
    }

    public function fetchNavigation()
    {
        return [
            'subpages' => [
                [
                    'location' => 'support',
                    'index' => 900,
                    'label' => 'Announcements',
                    'uri' => $this->di['url']->adminLink('news'),
                    'class' => '',
                ],
            ],
        ];
    }

    public function register(\Priyx_App &$app)
    {
        $app->get('/news', 'get_index', [], get_class($this));
        $app->get('/news/', 'get_index', [], get_class($this));
        $app->get('/news/index', 'get_index', [], get_class($this));
        $app->get('/news/index/', 'get_index', [], get_class($this));
        $app->get('/news/post/:id', 'get_post', ['id' => '[0-9]+'], get_class($this));
    }

    public function get_index(\Priyx_App $app)
    {
        $this->di['is_admin_logged'];

        return $app->render('mod_news_index');
    }

    public function get_post(\Priyx_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $post = $api->news_get(['id' => $id]);

        return $app->render('mod_news_post', ['post' => $post]);
    }
}
