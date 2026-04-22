<?php

namespace app\Storage;

use app\Model\CustomerModel;

class CustomerStorage extends AbstractStorage
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
    protected string $table = 'customers';

    /**
     * Field map
     *
     * [db_field_name] => classParamName
     *
     * @var array
     */
    protected array $fieldMap = array(
        'id'                    => 'id',
        'company_name'          => 'companyName',
        'primary_contact_name'  => 'primaryContactName',
        'primary_contact_email' => 'primaryContactEmail',
        'country_code'          => 'countryCode',
        'region'                => 'region',
        'industry'              => 'industry',
        'employee_band'         => 'employeeBand',
        'ai_maturity'           => 'aiMaturity',
        'status'                => 'status',
        'notes'                 => 'notes',
        'created_at'            => 'createdAt',
        'updated_at'            => 'updatedAt'
    );

    /**
     * Fetch all customers, ordered by company name.
     *
     * @return CustomerModel[]
     */
    public function getAll(): array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM customers
                ORDER BY company_name ASC';

        $sth = $pdo->prepare($sql);
        $sth->execute();

        return $sth->fetchAll($pdo::FETCH_CLASS, CustomerModel::class);
    }

    /**
     * Fetch a single customer by ID.
     *
     * @param int $id
     * @return CustomerModel|null
     */
    public function getById(int $id): ?CustomerModel
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT ' . $this->mapFields() . '
                FROM customers
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([':id' => $id]);

        return $sth->fetchObject(CustomerModel::class) ?: null;
    }

    /**
     * Update core profile fields for a customer.
     *
     * @param int $id
     * @param string $companyName
     * @param string $industry
     * @param string $employeeBand
     * @param string $aiMaturity
     * @param string $status
     * @param string $countryCode
     * @param string|null $region
     * @param string|null $primaryContactName
     * @param string|null $primaryContactEmail
     * @param string|null $notes
     * @return void
     */
    public function update(int $id, string $companyName, string $industry, string $employeeBand, string $aiMaturity, string $status, string $countryCode = 'DE', ?string $region = null, ?string $primaryContactName = null, ?string $primaryContactEmail = null, ?string $notes = null): void
    {
        $pdo = $this->getPdo();

        $sql = 'UPDATE customers
                SET company_name          = :companyName,
                    industry              = :industry,
                    employee_band         = :employeeBand,
                    ai_maturity           = :aiMaturity,
                    status                = :status,
                    country_code          = :countryCode,
                    region                = :region,
                    primary_contact_name  = :primaryContactName,
                    primary_contact_email = :primaryContactEmail,
                    notes                 = :notes
                WHERE id = :id';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':companyName'         => $companyName,
            ':industry'            => $industry,
            ':employeeBand'        => $employeeBand,
            ':aiMaturity'          => $aiMaturity,
            ':status'              => $status,
            ':countryCode'         => $countryCode,
            ':region'              => $region,
            ':primaryContactName'  => $primaryContactName,
            ':primaryContactEmail' => $primaryContactEmail,
            ':notes'               => $notes,
            ':id'                  => $id,
        ]);
    }
    
    /**
     * Fetch reports related to a customer.                     
     *                                                                            
     * @param int $customerId
     * @return array                                                              
     */             
    public function getReportsByCustomerId(int $customerId): array
    {                                                                             
        $pdo = $this->getPdo();

        $sql = 'SELECT r.id, r.status, r.canonical_scope_key, r.updated_at
                FROM reports r
                WHERE r.customer_id = :customerId
                ORDER BY r.updated_at DESC';                                      

        $sth = $pdo->prepare($sql);                                               
        $sth->execute([
            ':customerId' => $customerId
        ]);

        return $sth->fetchAll($pdo::FETCH_ASSOC);                                 
    }
    
    /**
     * Fetch the latest research run for a customer.
     *
     * @param int $customerId
     * @return array|null
     */
    public function getLatestRunByCustomerId(int $customerId): ?array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT rr.id, rr.status, rr.guardrail_status, rr.started_at
                FROM research_runs rr
                JOIN reports r ON r.run_id = rr.id
                WHERE r.customer_id = :customerId
                ORDER BY rr.id DESC
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':customerId' => $customerId
        ]);

        return $sth->fetch($pdo::FETCH_ASSOC) ?: null;
    }

    /**
     * Fetch the latest QA review status for a customer via research_runs and reports.
     *
     * @param int $customerId
     * @return array|null
     */
    public function getLatestQaStatusByCustomerId(int $customerId): ?array
    {
        $pdo = $this->getPdo();

        $sql = 'SELECT qr.decision_status, qr.decided_at
                FROM qa_reviews qr
                JOIN report_revisions rv ON rv.id = qr.report_revision_id
                JOIN reports r ON r.id = rv.report_id
                WHERE r.customer_id = :customerId
                ORDER BY qr.id DESC
                LIMIT 1';

        $sth = $pdo->prepare($sql);
        $sth->execute([
            ':customerId' => $customerId
        ]);

        return $sth->fetch($pdo::FETCH_ASSOC) ?: null;
    }
    
    /**                                         
     * Get counts of customers by status.                                                   
     *                                          
     * @return array                 
     */                                                                                     
    public function getStatusCounts(): array                                                
    {                                                                                       
        $pdo = $this->getPdo();                                                             

        $sql = 'SELECT                                                                      
                    COUNT(*) AS total,
                    SUM(status = :active) AS active,                               
                    SUM(status = :paused) AS paused,
                    SUM(status = :archived) AS archived
                FROM customers';                                                            

        $sth = $pdo->prepare($sql);                                                         
        $sth->execute([                     
            ':active'   => CustomerModel::STATUS_ACTIVE,                                    
            ':paused'   => CustomerModel::STATUS_PAUSED,                                    
            ':archived' => CustomerModel::STATUS_ARCHIVED,
        ]);                                                                                 

        $row = $sth->fetch();                                                               

        return [                                                                            
            'total'    => $row['total'] ?? 0,
            'active'   => $row['active'] ?? 0,
            'paused'   => $row['paused'] ?? 0,
            'archived' => $row['archived'] ?? 0,
        ];                                                                                  
    }

}
