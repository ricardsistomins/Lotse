<?php

namespace app\Model;

class CustomerModel                                                           
{
    public int     $id;                                                           
    public string  $companyName;
    public ?string $primaryContactName = null;
    public ?string $primaryContactEmail = null;
    public string  $countryCode;                                               
    public ?string $region = null;
    public string  $industry;                                                  
    public string  $employeeBand;                                              
    public string  $aiMaturity;
    public string  $status;                                                    
    public ?string $notes = null;
    public string  $createdAt;                                                 
    public string  $updatedAt;

    // run.status values                                                      
    const STATUS_ACTIVE   = 'active';
    const STATUS_PAUSED   = 'paused';                                         
    const STATUS_ARCHIVED = 'archived';

    // ai_maturity values
    const MATURITY_NONE       = 'none';                                       
    const MATURITY_EXPLORING  = 'exploring';
    const MATURITY_PILOT      = 'pilot';                                      
    const MATURITY_PRODUCTION = 'production';
}                                         