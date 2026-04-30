<?php

namespace app\controllers;

use app\Storage\CustomerStorage;
use app\Model\{
    CustomerModel,
    UserModel
};
use app\Service\AuditService;

class CustomerController extends BaseController
{
    /**
     * List all customers.
     *
     * @return void
     */
    public function indexAction(): void
    {
        $request = $this->request;
        $status = $request->getQuery('status', 'string') ?: null;
        $countryCode = $request->getQuery('country_code', 'string') ?: null;
        $page = max(1, $request->getQuery('page', 'int', 1));
        
        $customerStorage = new CustomerStorage();
        $total = $customerStorage->getCount($status, $countryCode);                                                                             
        $totalPages = max(1, (int) ceil($total / CustomerStorage::PER_PAGE));                                                                        
        $page = min($page, $totalPages);
        
        $this->view->setVars([
            'customers'      => $customerStorage->getAll($status, $countryCode, $page),                                                              
            'customersCount' => $customerStorage->getStatusCounts(),                                                                                 
            'countries'      => $customerStorage->getDistinctCountries(),                                                                            
            'filterStatus'   => $status,                                                                                                             
            'filterCountry'  => $countryCode,                                                                                                        
            'page'           => $page,
            'totalPages'     => $totalPages,                                                                                                         
            'total'          => $total
        ]);
    }

    /**
     * Show customer detail.
     *
     * @param int $id
     * @return void
     */
    public function viewAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $customerStorage  = new CustomerStorage();
        $customer = $customerStorage->getById($id);

        if (!$customer) {
            $this->langRedirect('/customers');
            return;
        }

        $this->view->setVars([
            'customer'  => $customer,
            'reports'   => $customerStorage->getReportsByCustomerId($id),
            'latestRun' => $customerStorage->getLatestRunByCustomerId($id),
            'latestQa'  => $customerStorage->getLatestQaStatusByCustomerId($id),
        ]);
    }

    /**
     * Save updated customer profile.
     *
     * @param int $id
     * @return void
     */
    public function saveAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $userRole = $this->session->get('userRole');

        if (!in_array($userRole, [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV])) {
            $this->langRedirect('/customer/' . $id);
            return;
        }

        $request = $this->request;

        (new CustomerStorage())->update(
            id:                  $id,
            companyName:         $request->getPost('company_name', 'string'),
            industry:            $request->getPost('industry', 'string'),
            employeeBand:        $request->getPost('employee_band', 'string'),
            aiMaturity:          $request->getPost('ai_maturity', 'string'),
            status:              $request->getPost('status', 'string'),
            countryCode:         $request->getPost('country_code', 'string') ?: 'DE',
            region:              $request->getPost('region', 'string') ?: null,
            primaryContactName:  $request->getPost('primary_contact_name', 'string') ?: null,
            primaryContactEmail: $request->getPost('primary_contact_email', 'email') ?: null,
            notes:               $request->getPost('notes', 'string') ?: null,
        );

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      'customer.updated',
            entityType:  'customer',
            entityId:    $id,
            metadata:    []
        );
 
        $this->langRedirect('/customer/' . $id);
    }
}
