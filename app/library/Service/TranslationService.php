<?php

namespace app\Service;

use Phalcon\Db\Adapter\Pdo\Mysql;                                             

class TranslationService                                                      
{               
    private array $translations = []; 
    private const CACHE_TIME = 3600;
    
    /**                                                                       
     * Load all translation strings for the given language from the database.
     *                                                                        
     * @param Mysql $db
     * @param string $lang                                                    
     */ 
    public function __construct(Mysql $db, string $lang) 
    {
        $cacheKey = 'translations_' . $lang;
        
        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey, $success);

            if ($success) {
                $this->translations = $cached;
                return;
            }
        }
        
        $pdo = $db->getInternalHandler();
        
        $sql = 'SELECT `key`, value
                FROM translations
                WHERE lang = :lang';
        
        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':lang' => $lang
        ]);
        
        foreach ($sth->fetchAll($pdo::FETCH_ASSOC) as $row) {
            $this->translations[$row['key']] = $row['value'];
        }
        
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $this->translations, self::CACHE_TIME);
        }
    }
    
    /**
     * Return the translated string for the given key.
     * Supports sprintf-style placeholders when params are provided.
     * Falls back to the key itself if no translation is found.               
     *                                                                        
     * @param string $key                                                   
     * @param array $params                                                
     * @return string
     */                                                                       
    public function translate(string $key, array $params = []): string
    {                                                                         
        $value = $this->translations[$key] ?? $key;

        return $params ? vsprintf($value, $params) : $value;                  
    }

}
      