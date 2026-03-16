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
}
