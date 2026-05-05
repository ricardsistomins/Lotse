<?php

namespace app\Plugin;                                                         
                  
use Phalcon\Di\Injectable;
use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;                                                   
use app\Service\TranslationService;
 
class LanguagePlugin extends Injectable
{
    private const SUPPORTED_LANGS = ['en', 'de'];
    private const DEFAULT_LANG = 'de';
    
    /**
     * Extract language from route param, load translations, and inject into view.                                                                         
     *
     * @param  Event      $event                                              
     * @param  Dispatcher $dispatcher
     * @return void                                                           
     */
    public function beforeExecuteRoute(Event $event, Dispatcher $dispatcher): void
    {
        $language = $dispatcher->getParam('lang') ?? $this->session->get('language') ?? self::DEFAULT_LANG;
        
        if (!in_array($language, self::SUPPORTED_LANGS, true)) {
            $language = self::DEFAULT_LANG;
        }
        
        $this->session->set('language', $language);
        
        $translationService = new TranslationService($this->db, $language);
        
        $this->view->setVars([
            'translate' => fn(string $key, array $params = []) => $translationService->translate($key, $params),
            'language'  => $language
        ]);
    }
}