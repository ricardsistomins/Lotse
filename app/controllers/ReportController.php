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
    ReportAnalyticsService
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
            'findings'          => $findings
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
        $findingType = $request->getPost('finding_type', 'string');
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
            $structuredPayload = json_decode($revision->structuredPayload ?? '[]', true);
            $analyticsData = (new ReportAnalyticsService())->generate($id, $revision->id, $structuredPayload, $revision->finalMarkdown ?? '');
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
}
