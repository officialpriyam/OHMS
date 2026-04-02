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

namespace Priyx\Mod\Aichat;

class Service implements \Priyx\InjectionAwareInterface
{
    protected $di;

    public function setDi($di)
    {
        $this->di = $di;
    }

    public function getDi()
    {
        return $this->di;
    }

    public function getSettings()
    {
        return $this->di['mod_config']('aichat');
    }

    public function ask($prompt, $history = [])
    {
        $config = $this->getSettings();
        $provider = $config['provider'] ?? 'gemini';
        
        switch ($provider) {
            case 'gemini':
                return $this->askGemini($prompt, $config, $history);
            case 'openrouter':
                return $this->askOpenRouter($prompt, $config, $history);
            case 'chatgpt':
                return $this->askChatGPT($prompt, $config, $history);
            case 'claude':
                return $this->askClaude($prompt, $config, $history);
            default:
                throw new \Exception('AI Provider not supported: ' . $provider);
        }
    }

    private function askGemini($prompt, $config, $history)
    {
        $api_key = $config['gemini_api_key'] ?? '';
        $model = $config['gemini_model'] ?? 'gemini-pro';
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";
        
        $contents = [];
        foreach ($history as $msg) {
            $contents[] = [
                'role' => ($msg['role'] == 'user') ? 'user' : 'model',
                'parts' => [['text' => $msg['content']]]
            ];
        }
        $contents[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];

        $data = ['contents' => $contents];
        
        $response = $this->curlRequest($url, $data);
        $result = json_decode($response, true);
        
        return $result['candidates'][0]['content']['parts'][0]['text'] ?? 'Sorry, I could not generate a response.';
    }

    private function askOpenRouter($prompt, $config, $history)
    {
        $api_key = $config['openrouter_api_key'] ?? '';
        $model = $config['openrouter_model'] ?? 'google/gemini-pro-1.5';
        
        $url = "https://openrouter.ai/api/v1/chat/completions";
        
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $data = [
            'model' => $model,
            'messages' => $messages
        ];
        
        $headers = [
            'Authorization: Bearer ' . $api_key,
            'HTTP-Referer: https://ohms.priyxstudio.in',
            'X-Title: OHMS Support'
        ];
        
        $response = $this->curlRequest($url, $data, $headers);
        $result = json_decode($response, true);
        
        return $result['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response from OpenRouter.';
    }

    private function askChatGPT($prompt, $config, $history)
    {
        $api_key = $config['chatgpt_api_key'] ?? '';
        $model = $config['chatgpt_model'] ?? 'gpt-3.5-turbo';
        
        $url = "https://api.openai.com/v1/chat/completions";
        
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $data = [
            'model' => $model,
            'messages' => $messages
        ];
        
        $headers = [
            'Authorization: Bearer ' . $api_key
        ];
        
        $response = $this->curlRequest($url, $data, $headers);
        $result = json_decode($response, true);
        
        return $result['choices'][0]['message']['content'] ?? 'Sorry, I could not generate a response from ChatGPT.';
    }

    private function askClaude($prompt, $config, $history)
    {
        $api_key = $config['claude_api_key'] ?? '';
        $model = $config['claude_model'] ?? 'claude-3-opus-20240229';
        
        $url = "https://api.anthropic.com/v1/messages";
        
        $messages = [];
        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $data = [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => $messages
        ];
        
        $headers = [
            'x-api-key: ' . $api_key,
            'anthropic-version: 2023-06-01'
        ];
        
        $response = $this->curlRequest($url, $data, $headers);
        $result = json_decode($response, true);
        
        return $result['content'][0]['text'] ?? 'Sorry, I could not generate a response from Claude.';
    }

    private function curlRequest($url, $data, $headers = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $default_headers = ['Content-Type: application/json'];
        curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge($default_headers, $headers));
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            throw new \Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);
        
        return $response;
    }
}
