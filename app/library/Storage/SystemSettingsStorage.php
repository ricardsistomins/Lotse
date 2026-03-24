<?php

namespace app\Storage;

class SystemSettingsStorage extends AbstractStorage
{
    /**
     * Primary key
     *
     * @var string
     */
    protected string $primary = 'id';

    /**
     * Table name
     *
     * @var string
     */
    protected string $table = 'system_settings';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                 => 'id',
        'setting_key'        => 'settingKey',
        'setting_value_json' => 'settingValueJson',
        'description'        => 'description',
        'updated_by_user_id' => 'updatedByUserId',
        'updated_at'         => 'updatedAt'
    );
 
    /**
    * Get a decoded setting value by key.
    * Returns the parsed JSON value, or null if the key does not exist.
    */
    public function get(string $key): mixed
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT setting_value_json
                FROM system_settings 
                WHERE setting_key = :key';
        
        $sth = $pdo->prepare($sql);
        
        $sth->execute([
            ':key' => $key
        ]);

        $row = $sth->fetch();

        if (!$row) {
            return null;
        }

        return json_decode($row['setting_value_json'], true);
    }
    
    /**
     * Insert or update a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @param int|null $updatedByUserId
     * @return void
     */
    public function set(string $key, mixed $value, ?int $updatedByUserId = null): void
    {
        $pdo = $this->getPdo();

        $sql = 'INSERT INTO system_settings 
                    (setting_key, setting_value_json, updated_by_user_id, updated_at)
                VALUES 
                    (:key, :value, :updatedByUserId, :updatedAt)
                ON DUPLICATE KEY UPDATE
                    setting_value_json  = :value,
                    updated_by_user_id  = :updatedByUserId,
                    updated_at          = :updatedAt';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':key'             => $key,
            ':value'           => json_encode($value),
            ':updatedByUserId' => $updatedByUserId,
            ':updatedAt'       => date('Y-m-d H:i:s'),
        ]);
    }
    
    /**
     * Fetch all settings rows, ordered by key.                                   
     *                                                                            
     * @return array
     */                                                                           
    public function getAll(): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT setting_key, description, updated_at
                FROM system_settings                                              
                ORDER BY setting_key ASC';

        $sth = $pdo->prepare($sql);                                               
        $sth->execute();

        return $sth->fetchAll($pdo::FETCH_ASSOC);
    }
}
