<?php

namespace app\controllers;

use app\Storage\ {
    ReportStorage,
    ReportRevisionStorage,
    ResearchRunStorage,
    ResearchSourceStorage,
    ResearchFindingStorage,
    CustomerStorage,
    AuditLogStorage,
    ReportAnalyticsStorage
};
use app\Model\{
    ReportModel,
    ResearchRunModel,
    UserModel
};
use app\Service\{
    AuditService,
    DuplicateRunException,
    ReportAnalyticsService,
    PdfService
};

class ReportController extends BaseController
{
    /**
     * List all reports
     */
    public function indexAction()
    {
        $request = $this->request;
        
        $status = $request->getQuery('status', 'string') ?: null;
        $customerId = $request->getQuery('customer_id', 'int') ?: null;
        $page = max(1, $request->getQuery('page', 'int', 1));

        $customers = (new CustomerStorage())->getAll();
        $customersName = [];
        
        foreach ($customers as $customer) {
            $customersName[$customer->id] = $customer->companyName;
        }
               
        $reportStorage = new ReportStorage();
        $total = $reportStorage->getCount($status, $customerId);
        $totalPages = max(1, ceil($total / ReportStorage::PER_PAGE));
        $page = min($page, $totalPages);
        
        $this->view->setVars([
            'reports'        => $reportStorage->getAll($status, $customerId, $page),
            'customers'      => $customers,
            'customersName'  => $customersName,
            'filterStatus'   => $status,
            'filterCustomer' => $customerId,
            'getStatusCount' => $reportStorage->getStatusCount(),
            'page'           => $page,
            'total'          => $total,
            'totalPages'     => $totalPages
        ]);
    }

    /**
     * Show report editor with current content and revision history.
     */
    public function viewAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $view = $this->view;
        $session = $this->session;

        $report = (new ReportStorage())->getById($id);

        if (!$report) {
            $this->langRedirect('/dashboard');
            return;
        }

        $run = (new ResearchRunStorage())->getById($report->runId);
        $sourceCount = (new ResearchSourceStorage())->countByRunId($report->runId);
        $findingCount = (new ResearchFindingStorage())->countByRunId($report->runId);

        $findings = (new ResearchFindingStorage())->getAllByRunId($report->runId);
        
        $revisionStorage = new ReportRevisionStorage();
        $latestRevision  = $revisionStorage->getById($report->currentRevisionId ?? 0);

        $finalMarkdown = $latestRevision->finalMarkdown ?? '';
        $structuredPayload = json_decode($latestRevision->structuredPayload ?? '[]', true);

        $renderedHtml = (new \Parsedown())->text($finalMarkdown);
        $revisions    = $revisionStorage->getAllByReportId($id);

        $role   = $session->get('userRole', 'string');
        $canAct = in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_QA]);                                                                         
        
        $flash = $this->session->get('_flash');                                                                       
        $this->session->remove('_flash');
        
        $view->setVars([
            'report'            => $report,
            'revisions'         => $revisions,
            'canAct'            => $canAct,
            'run'               => $run,
            'sourceCount'       => $sourceCount,
            'findingCount'      => $findingCount,
            'finalMarkdown'     => $finalMarkdown,
            'structuredPayload' => $structuredPayload,
            'renderedHtml'      => $renderedHtml,
            'customers'         => (new CustomerStorage())->getAll(),
            'customerId'        => $report->customerId,
            'auditLog'          => (new AuditLogStorage())->getAllByEntity('report', $id),
            'findings'          => $findings,
            'flash'             => $flash
        ]);
    }

    /**
     * Save edited content as a new revision.
     */
    public function saveAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $request = $this->request;
        $session = $this->session;

        if (!$request->isPost()) {
            $this->langRedirect('/report/' . $id);
            return;
        }

        $finalMarkdown = $request->getPost('final_markdown', 'string');
        $userId = $session->get('userId', 'int');

        $revisionId = (new ReportRevisionStorage())->save($id, [], $finalMarkdown, $userId);
        (new ReportStorage())->setCurrentRevision($id, $revisionId);

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      'report.saved',
            entityType:  'report',
            entityId:    $id,
            metadata:    ['revision_id' => $revisionId]
        );
        
        $this->langRedirect('/report/' . $id);
    }

    /**
     * Approve or reject a report. Admin and QA only.
     */
    public function updateStatusAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $request = $this->request;
        $session = $this->session;
        
        if (!$request->isPost()) {
            $this->langRedirect('/report/' . $id);
            return;
        }

        $role = $session->get('userRole', 'string');

        if (!in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_QA])) {
            $this->langRedirect('/report/' . $id);
            return;
        }

        $status = $request->getPost('status', 'string');
        $userId = $session->get('userId', 'int');

        if (!in_array($status, [ReportModel::STATUS_APPROVED, ReportModel::STATUS_REJECTED])) {
            $this->langRedirect('/report/' . $id);
            return;
        }

        // Block approval if run guardrail is blocked
        if ($status === ReportModel::STATUS_APPROVED) {
            $report = (new ReportStorage())->getById($id);
            $run    = (new ResearchRunStorage())->getById($report->runId);

            if (($run->guardrailStatus ?? '') === ResearchRunModel::STATUS_BLOCKED) {
                $this->langRedirect('/report/' . $id);
                return;
            }
        }

        (new ReportStorage())->updateStatus($id, $status, $userId);

        $report     = $report ?? (new ReportStorage())->getById($id);
        $revisionId = $report->currentRevisionId ?? null;

        if ($status === ReportModel::STATUS_APPROVED && $revisionId) {
            (new ReportStorage())->setApprovedRevision($id, $revisionId);
        }

        $action = $status === ReportModel::STATUS_APPROVED ? 'report.approved' : 'report.rejected';

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      $action,
            entityType:  'report',
            entityId:    $id,
            metadata:    [
                'status'      => $status,
                'revision_id' => $revisionId
            ]
        );

        $this->langRedirect('/report/' . $id);
    }
    
    /**
     * Re-trigger research for this report. Admin, dev, and QA only.
     * 
     * @return void
     */
    public function retriggerAction(): void 
    {     
        $id = (int)$this->dispatcher->getParam('id');
        $session = $this->session;
        
        if (!$this->request->isPost()) {
            $this->langRedirect('/report/' . $id);
            return;
        }
        
        $role = $session->get('userRole', 'string');
        $userId = $session->get('userId', 'int');
        
        if (!in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_DEV, UserModel::ROLE_QA])) {
            $this->setFlash('danger', $this->translate('You do not have permission to re-trigger reports.'));
            $this->langRedirect('/report/' . $id);
            return;
        }
            
        $report = (new ReportStorage())->getById($id);
        
        if (!$report) {
            $this->langRedirect('/dashboard');
            return;
        }
        
        $run = (new ResearchRunStorage())->getById($report->runId);
    
        if (!$run || empty($run->query)) {
            $this->setFlash('danger', $this->translate('Cannot re-trigger: original run data is missing.'));
            $this->langRedirect('/report/' . $id);
            return;
        }
     
        $triggerSource = match($role) {
            UserModel::ROLE_ADMIN => ResearchRunModel::TRIGGER_DASHBOARD_ADMIN,                                  
            UserModel::ROLE_DEV   => ResearchRunModel::TRIGGER_DASHBOARD_DEV,                                    
            default               => ResearchRunModel::TRIGGER_DASHBOARD_QA
        };
      
        try {
            $newRunId = $this->orchestrator->dispatch($triggerSource, $run->query, $userId, ResearchRunModel::RUN_TYPE_REPORT_RETRIGGER);
        } catch (DuplicateRunException $ex) {
            $this->setFlash('warning', $this->translate('A research run for this query is already in progress.'));
            $this->langRedirect('/report/' . $id);
            return;
        }
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,                                                                                
            action:      'report.retriggered',
            entityType:  'report',                                                                               
            entityId:    $id,
            metadata:    ['new_run_id' => $newRunId]
        );  
        
        $this->setFlash('success', $this->translate('Re-trigger started. A new research run has been queued.')); 
        $this->langRedirect('/report/' . $id);
    }
    
    /**
     * Assign a customer to a report.
     *
     * @param int $id
     * @return void
     */
    public function saveCustomerAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $request  = $this->request;

        if (!$request->isPost()) {
            $this->langRedirect('/report/' . $id);
            return;
        }

        $customerId = $request->getPost('customer_id', 'int') ?: null;
        $userId     = (int)$this->session->get('userId');

        (new ReportStorage())->updateCustomer($id, $customerId);

        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,
            action:      'report.customer_assigned',
            entityType:  'report',
            entityId:    $id,
            metadata:    [
                'customer_id' => $customerId
            ]
        );

        $this->langRedirect('/report/' . $id);
    }
    
    /**
     * Edit a single finding field. Admin and QA only.
     * 
     * @param int $id
     * @return void
     */
    public function editFindingAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $request = $this->request;
        $session = $this->session;
        
        $role = $session->get('userRole', 'string');
        
        if (!$request->isPost() || !in_array($role, [UserModel::ROLE_ADMIN, UserModel::ROLE_QA])) {
            $this->langRedirect('/dashboard');
            return;
        }
        
        $findingStorage = new ResearchFindingStorage();
        $finding = $findingStorage->getById($id);
        
        if (!$finding) {
            $this->langRedirect('/dashboard');
            return;
        }
        
        $reportId = $request->getPost('report_id', 'int');
        $title = $request->getPost('title', 'string');
        $findingType = $request->getPost('finding_type', 'string') ?: $finding->findingType;
        $deadline = $request->getPost('deadline', 'string') ?: null;
        $userId = $session->get('userId', 'int');
        
        $before = [
            'title'        => $finding->title,
            'finding_type' => $finding->findingType,
            'deadline'     => $finding->deadline
        ];
        
        $findingStorage->update($id, $title, $findingType, $deadline);
        
        $after = [
            'title'        => $title,
            'finding_type' => $findingType,
            'deadline'     => $deadline
        ];
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: $userId,                                                 
            action:      'finding.edited',
            entityType:  'finding',                                               
            entityId:    $id,
            before:      $before,                                                 
            after:       $after,
            metadata:    ['report_id' => $reportId]   
        );
        
        $this->langRedirect('/report/' . $reportId);
    }
    
    /**
     * Generate or load analytics and render the HTML preview
     * 
     * @param int $id
     * @return void
     */
    public function previewAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $view = $this->view;
        
        $report = (new ReportStorage())->getById($id);
        
        if (!$report) {
            $this->langRedirect('/dashboard');
            return;
        }
        
        $revisionStorage = new ReportRevisionStorage();
        $revision = $revisionStorage->getById($report->currentRevisionId ?? 0);
        
        if (!$revision) {
            $this->langRedirect('/report/' . $id);
            return;
        }
        
        $analyticsStorage = new ReportAnalyticsStorage();
        $analytics = $analyticsStorage->getByRevisionId($revision->id);
 
        if (!$analytics) {
            $cmd = 'php ' . BASE_PATH . '/cli.php analytics generate ' . $id . ' ' . $revision->id . ' > /dev/null 2>&1 &'; 
            exec($cmd);
            
            $view->setVars([
                'report' => $report,
                'generating' => true
            ]);
            
            return;
        } else {
            $analyticsData = json_decode($analytics->analyticsPayload, true);
        }
        
        $view->setVars([
            'report'            => $report,
            'revision'          => $revision,
            'analytics'         => $analyticsData,
            'structuredPayload' => json_decode($revision->structuredPayload ?? '[]', true)
        ]);
    }
    
    /**
     * Return JSON indicating whether analytics are ready for the given report.
     * Used by the preview page to poll for background generation completion.
     * 
     * @return void
     */
    public function analyticsStatusAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $report = (new ReportStorage())->getById($id);
        $ready = false;
        
        if ($report) {
            $revision = (new ReportRevisionStorage())->getById($report->currentRevisionId ?? 0);
            
            if ($revision) {
                $ready = (new ReportAnalyticsStorage())->getByRevisionId($revision->id) !== null;
            }
        }
        
        $response = $this->response;
        
        $response->setContentType('application/json');
        $response->setContent(json_encode(['ready' => $ready]));
        $response->send();
        $this->view->disable();
    }
    
    /**
     * Export approved report as a PDF download
     * 
     * @return void
     */
    public function exportAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $report = (new ReportStorage())->getById($id);
        
        $allowedStatuses = [ReportModel::STATUS_APPROVED, ReportModel::STATUS_ARCHIVED];
        
        if (!$report || !in_array($report->status, $allowedStatuses) || !$report->approvedRevisionId) {
            $this->langRedirect('/reports');
            return;
        }
        
        $revision = (new ReportRevisionStorage())->getById($report->approvedRevisionId);
        $run = (new ResearchRunStorage())->getById($report->runId);
        $customer = $report->customerId ? (new CustomerStorage())->getById($report->customerId) : null;
        $customerName = $customer?->companyName;
        $parsedown = fn(string $md) => (new \Parsedown())->text($md);
        
        $html = $this->view->getRender('report', 'pdf/pdf', [
            'report'       => $report,
            'revision'     => $revision,
            'customerName' => $customerName,
            'parsedown'    => $parsedown,
            'run'          => $run
        ], function($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });
        
        $filename = 'report-' . $id . '-' . date('Y-m-d') . '.pdf';
        $pdf = (new PdfService())->render($html);
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      'report.exported',
            entityType:  'report',
            entityId:    $id,
            metadata:    ['filename' => $filename]
        );
        
        $this->view->disable();
        
        $response = $this->response;
        $response->setContentType('application/pdf');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setContent($pdf);
        $response->send();
    }
    
    public function exportPreviewAction(): void
    {
        $id = (int)$this->dispatcher->getParam('id');
        $report = (new ReportStorage())->getById($id);
        
        $allowedStatuses = [ReportModel::STATUS_APPROVED, ReportModel::STATUS_ARCHIVED];
        
        if (!$report || !in_array($report->status, $allowedStatuses) || !$report->approvedRevisionId) {
            $this->langRedirect('/reports');
            return;
        }
        
        $revision = (new ReportRevisionStorage())->getById($report->approvedRevisionId);
        $run = (new ResearchRunStorage())->getById($report->runId);
        $customer = $report->customerId ? (new CustomerStorage())->getById($report->customerId) : null;
        $customerName = $customer?->companyName;
        $parsedown = fn(string $md) => (new \Parsedown())->text($md);
        
        $analyticsStorage = new ReportAnalyticsStorage;
        $analytics = $analyticsStorage->getByRevisionId($revision->id);
        $analyticsData = $analytics ? json_decode($analytics->analyticsPayload, true) : (new ReportAnalyticsService())->generate($id, $revision->id, json_decode($revision->structuredPayload ?? '[]', true), $revision->finalMarkdown ?? '');
        
        $html = $this->view->getRender('report', 'pdf/pdf-preview', [
            'report'            => $report,
            'revision'          => $revision,
            'customerName'      => $customerName,
            'parsedown'         => $parsedown,
            'analytics'         => $analyticsData,
            'structuredPayload' => json_decode($revision->structuredPayload ?? '[]', true),
            'language'          => $this->session->get('language') ?? 'en',
            'run'               => $run
        ], function($view) {
            $view->setRenderLevel(\Phalcon\Mvc\View::LEVEL_ACTION_VIEW);
        });
        
        $filename = 'report-preview-' . $id . '-' . date('Y-m-d') . '.pdf';
        $pdf = (new PdfService())->render($html);
        
        (new AuditService($this->db))->log(
            actorType:   'user',
            actorUserId: (int)$this->session->get('userId'),
            action:      'report.exported',
            entityType:  'report',
            entityId:    $id,
            metadata:    ['filename' => $filename]
        );
        
        $this->view->disable();
        
        $response = $this->response;
        $response->setContentType('application/pdf');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $response->setContent($pdf);
        $response->send();
    }
}
