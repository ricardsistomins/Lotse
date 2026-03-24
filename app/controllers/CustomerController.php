<?php

namespace app\controllers;

use Phalcon\Mvc\Controller;

use app\Storage\CustomerStorage;
use app\Model\CustomerModel;

class CustomerController extends Controller
{
    /**
     * List all customers.
     *
     * @return void
     */
    public function indexAction(): void
    {
        $this->view->setVar('customers', (new CustomerStorage())->getAll());
    }

    /**
     * Show customer detail.
     *
     * @param int $id
     * @return void
     */
    public function viewAction(int $id): void
    {
        $response = $this->response;
        $customerStorage  = new CustomerStorage();
        $customer = $customerStorage->getById($id);

        if (!$customer) {
            $response->redirect('/customers');
            $response->send();

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
    public function saveAction(int $id): void
    {
        $response = $this->response;
        $userRole = $this->session->get('userRole');

        if (!in_array($userRole, ['admin', 'dev'])) {
            $response->redirect('/customer/' . $id);
            $response->send();

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

        $response->redirect('/customer/' . $id);
        $response->send();
    }
}
