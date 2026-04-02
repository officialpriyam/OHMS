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



use Priyx\InjectionAwareInterface;

class Priyx_LatteExtensions implements InjectionAwareInterface
{
    protected $di;
    protected $currentTheme;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function setCurrentTheme($theme)
    {
        $this->currentTheme = $theme;
    }

    /**
     * Register all custom filters on a Latte\Engine instance.
     */
    public function register(\Latte\Engine $latte)
    {
        // Translation
        $latte->addFilter('trans', function ($value) {
            return gettext($value);
        });

        // URL helpers
        $latte->addFilter('link', function ($link, $params = null) {
            if (null === $this->di['url']) {
                return null;
            }
            return $this->di['url']->link($link, $params);
        });

        $latte->addFilter('alink', function ($link, $params = null) {
            return $this->di['url']->adminLink($link, $params);
        });

        // Asset URL
        $latte->addFilter('asset_url', function ($asset) {
            return PS_URL . 'themes/' . $this->currentTheme . '/assets/' . $asset;
        });

        $latte->addFilter('mod_asset_url', function ($asset, $mod) {
            return PS_URL . 'modules/' . ucfirst($mod) . '/assets/' . $asset;
        });

        // Gravatar
        $latte->addFilter('gravatar', function ($email, $size = 20) {
            return (new Priyx_Tools())->get_gravatar($email, $size);
        });

        // Markdown
        $latte->addFilter('markdown', function ($value) {
            $markdownParser = new \Michelf\MarkdownExtra();
            $markdownParser->hard_wrap = true;
            $result = $markdownParser->transform(htmlspecialchars((string) ($value ?? ''), ENT_NOQUOTES));
            $result = preg_replace_callback('/(?<=href=")(.*)(?=")/', function ($match) {
                if (!filter_var($match[0], FILTER_VALIDATE_URL)) {
                    $match[0] = '#';
                }
                return $match[0];
            }, $result);
            return new \Latte\Runtime\Html($result);
        });

        // Truncate
        $latte->addFilter('truncate', function ($value, $length = 30, $preserve = false, $separator = '...') {
            mb_internal_encoding('UTF-8');
            $value = (string) ($value ?? '');
            if (mb_strlen($value) > $length) {
                if ($preserve) {
                    if (false !== ($breakpoint = mb_strpos($value, ' ', $length))) {
                        $length = $breakpoint;
                    }
                }
                return mb_substr($value, 0, $length) . $separator;
            }
            return $value;
        });

        // Time ago
        $latte->addFilter('timeago', function ($iso8601) {
            return $this->timeago($iso8601);
        });

        // Days left
        $latte->addFilter('daysleft', function ($iso8601) {
            return $this->daysleft($iso8601);
        });

        // File size
        $latte->addFilter('size', function ($value) {
            return $this->sizeFilter($value);
        });

        // Number
        $latte->addFilter('number', function ($number, $decimals = 2, $dec_point = '.', $thousands_sep = '') {
            return number_format((float) ($number ?? 0), $decimals, $dec_point, $thousands_sep);
        });

        // IP country name
        $latte->addFilter('ipcountryname', function ($value) {
            return $this->twig_ipcountryname_filter($value);
        });

        // Autolink
        $latte->addFilter('autolink', function ($text) {
            return new \Latte\Runtime\Html($this->autolink($text));
        });

        // Money filters
        $latte->addFilter('money', function ($price, $currency = null) {
            $api_guest = $this->di['api_guest'];
            return new \Latte\Runtime\Html(
                $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => false])
            );
        });

        $latte->addFilter('money_without_currency', function ($price, $currency = null) {
            $api_guest = $this->di['api_guest'];
            return new \Latte\Runtime\Html(
                $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => false, 'without_currency' => true])
            );
        });

        $latte->addFilter('money_convert', function ($price, $currency = null) {
            $api_guest = $this->di['api_guest'];
            if (is_null($currency)) {
                $c = $api_guest->cart_get_currency();
                $currency = $c['code'];
            }
            return new \Latte\Runtime\Html(
                $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => true])
            );
        });

        $latte->addFilter('money_convert_without_currency', function ($price, $currency = null) {
            $api_guest = $this->di['api_guest'];
            if (is_null($currency)) {
                $c = $api_guest->cart_get_currency();
                $currency = $c['code'];
            }
            return new \Latte\Runtime\Html(
                $api_guest->currency_format(['price' => $price, 'code' => $currency, 'convert' => true, 'without_currency' => true])
            );
        });

        // Period title 
        $latte->addFilter('period_title', function ($period) {
            $api_guest = $this->di['api_guest'];
            return new \Latte\Runtime\Html(
                $api_guest->system_period_title(['code' => $period])
            );
        });

        // Date formatting
        $latte->addFilter('bb_date', function ($time, $format = null) {
            $locale_date_format = $this->di['config']['locale_date_format'];
            $format = is_null($format) ? $locale_date_format : $format;
            return strftime($format, strtotime($time));
        });

        $latte->addFilter('bb_datetime', function ($time, $format = null) {
            $locale_date_format = $this->di['config']['locale_date_format'];
            $locale_time_format = $this->di['config']['locale_time_format'];
            $format = is_null($format) ? $locale_date_format . $locale_time_format : $format;
            return strftime($format, strtotime($time));
        });

        // HTML tag helpers
        $latte->addFilter('img_tag', function ($path, $alt = null) {
            $alt = is_null($alt) ? pathinfo($path, PATHINFO_BASENAME) : $alt;
            return new \Latte\Runtime\Html(
                sprintf('<img src="%s" alt="%s" title="%s"/>', htmlspecialchars($path), htmlspecialchars($alt), htmlspecialchars($alt))
            );
        });

        $latte->addFilter('script_tag', function ($path) {
            return new \Latte\Runtime\Html(
                sprintf('<script type="text/javascript" src="%s?%s"></script>', $path, Priyx_Version::VERSION)
            );
        });

        $latte->addFilter('stylesheet_tag', function ($path, $media = 'screen') {
            return new \Latte\Runtime\Html(
                sprintf('<link rel="stylesheet" type="text/css" href="%s?v=%s" media="%s" />', $path, Priyx_Version::VERSION, $media)
            );
        });

        // OHMS markdown (bbmd)
        $latte->addFilter('bbmd', function ($value) {
            $markdownParser = new \Michelf\MarkdownExtra();
            $markdownParser->hard_wrap = true;
            $result = $markdownParser->transform(htmlspecialchars((string) ($value ?? ''), ENT_NOQUOTES));
            $result = preg_replace_callback('/(?<=href=")(.*)(?=")/', function ($match) {
                if (!filter_var($match[0], FILTER_VALIDATE_URL)) {
                    $match[0] = '#';
                }
                return $match[0];
            }, $result);
            return new \Latte\Runtime\Html($result);
        });

        // Length filter (for arrays/strings)
        $latte->addFilter('length', function ($value) {
            if (is_array($value) || $value instanceof \Countable) {
                return count($value);
            }
            return mb_strlen((string) $value);
        });
    }

    public function twig_ipcountryname_filter($value)
    {
        if (empty($value)) {
            return '';
        }
        try {
            $record = $this->di['geoip']->country($value);
            return $record->country->name;
        } catch (\Exception $e) {
            return '';
        }
    }

    private function timeago($value)
    {
        if (empty($value)) {
            return '';
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$timestamp) {
            return '';
        }

        $diff = time() - $timestamp;
        if ($diff <= 0) {
            return '0 seconds';
        }

        $units = [
            'year' => 31536000,
            'month' => 2592000,
            'week' => 604800,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        foreach ($units as $label => $seconds) {
            if ($diff >= $seconds) {
                $amount = (int) floor($diff / $seconds);
                return $amount . ' ' . $label . ($amount === 1 ? '' : 's');
            }
        }

        return '0 seconds';
    }

    private function daysleft($value)
    {
        if (empty($value)) {
            return 0;
        }

        $timestamp = is_numeric($value) ? (int) $value : strtotime((string) $value);
        if (!$timestamp) {
            return 0;
        }

        return max(0, (int) ceil(($timestamp - time()) / 86400));
    }

    private function sizeFilter($value)
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $value = (float) $value;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return round($value, $index === 0 ? 0 : 2) . ' ' . $units[$index];
    }

    private function autolink($text)
    {
        $escaped = htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

        $escaped = preg_replace_callback(
            '~(https?://[^\s<]+)~i',
            static function ($matches) {
                $url = $matches[1];
                return sprintf('<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>', $url);
            },
            $escaped
        );

        $escaped = preg_replace_callback(
            '/(?<!=")(?<!">)([A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/i',
            static function ($matches) {
                $email = $matches[1];
                return sprintf('<a href="mailto:%1$s">%1$s</a>', $email);
            },
            $escaped
        );

        return $escaped;
    }
}
