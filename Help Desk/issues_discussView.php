<?php
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

use Gibbon\Tables\DataTable;
use Gibbon\Tables\Action;
use Gibbon\Services\Format;
use Gibbon\Module\HelpDesk\Domain\IssueDiscussGateway;
use Gibbon\Module\HelpDesk\Domain\IssueGateway;
use Gibbon\Module\HelpDesk\Domain\TechGroupGateway;
use Gibbon\Module\HelpDesk\Domain\TechnicianGateway;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\System\DiscussionGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\View\View;

require_once __DIR__ . '/moduleFunctions.php';

$page->breadcrumbs->add(__('Discuss Issue'));

if (!isModuleAccessible($guid, $connection2)) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $issueID = $_GET['issueID'] ?? '';

    $issueGateway = $container->get(IssueGateway::class);
    $issue = $issueGateway->getByID($issueID);

    if (empty($issueID) || empty($issue)) {
        $page->addError(__('No Issue Selected.'));
    } else {
        //Set up gateways
        $techGroupGateway = $container->get(TechGroupGateway::class);
        $technicianGateway = $container->get(TechnicianGateway::class);

        //Information about the current user
        $isPersonsIssue = ($issue['gibbonPersonID'] == $gibbon->session->get('gibbonPersonID'));
        $isTechnician = $technicianGateway->getTechnicianByPersonID($gibbon->session->get('gibbonPersonID'))->isNotEmpty();
        $isRelated = $issueGateway->isRelated($issueID, $gibbon->session->get('gibbonPersonID'));
        $hasFullAccess = $techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'fullAccess');

        //Information about the issue's technician
        $technician = $technicianGateway->getTechnician($issue['technicianID']);
        $technician = $technician->isNotEmpty() ? $technician->fetch() : [];
        $hasTechAssigned = !empty($technician);

        $allowed = $isRelated
            || (!$hasTechAssigned && $isTechnician) 
            || $hasFullAccess;

        $privacySetting = $issue['privacySetting'];
        if ($issue['status'] == 'Resolved' && !$hasFullAccess) {
            if ($privacySetting == 'No one') {
                $allowed = false;
            } else if ($privacySetting == 'Related' && !$isRelated) {
                $allowed = false;
            }
            else if ($privacySetting == 'Owner' && !$isPersonsIssue) {
                $allowed = false;
            }
        }

        if ($allowed) {
            if (isset($_GET['return'])) {
                returnProcess($guid, $_GET['return'], null, null);
            }

            if ($hasTechAssigned) {
                $technicianName = Format::name($technician['title'] , $technician['preferredName'] , $technician['surname'] , 'Student');
            } else {
                $technicianName = __('Unassigned');
            }
        
            $createdByShow = ($issue['createdByID'] != $issue['gibbonPersonID']);

            $date = dateConvertBack($guid, $issue['date']);
            if ($date == '30/11/-0001') {
                $date = 'No date';
            }

            $userGateway = $container->get(UserGateway::class);
            $owner = $userGateway->getByID($issue['gibbonPersonID']);

            $detailsData = array(
                'issueID' => $issueID,
                'owner' => Format::name($owner['title'] , $owner['preferredName'] , $owner['surname'] , 'Student'),
                'technician' => $technicianName,
                'date' => $date,
                'privacySetting' => $issue['privacySetting']
            );

            $tdWidth = count($detailsData);
            if ($createdByShow) {
                $tdWidth++;
            }
            $tdWidth = 100 / $tdWidth;
            $tdWidth .= '%';

            $table = DataTable::createDetails('details');
            $table->setTitle($issue['issueName']);

            $table->addColumn('issueID', __('ID'))
                    ->width($tdWidth)
                    ->format(Format::using('number', ['issueID', 0]));

            $table->addColumn('owner', __('Owner'))
                    ->width($tdWidth);

            $table->addColumn('technician', __('Technician'))
                    ->width($tdWidth);

            $table->addColumn('date', __('Date'))
                    ->width($tdWidth);

            if ($createdByShow) {
                $createdBy = $userGateway->getByID($issue['createdByID']);
                $detailsData['createdBy'] = Format::name($createdBy['title'] , $createdBy['preferredName'] , $createdBy['surname'] , 'Student');
                $table->addColumn('createdBy', __('Created By'))
                    ->width($tdWidth);
            }

            $table->addColumn('privacySetting', __('Privacy'))
                    ->width($tdWidth)
                    ->format(function($row) use ($gibbon, $isPersonsIssue, $hasFullAccess) {
                        if ($isPersonsIssue || $hasFullAccess) {
                            return '<a href="' . $gibbon->session->get('absoluteURL') . '/index.php?q=/modules/' . $gibbon->session->get('module') . '/issues_discussEdit.php&issueID='. $row['issueID'] . '">' .  __($row['privacySetting']) . '</a>';
                        } else {
                            return __($row['privacySetting']);
                        }
                    });

            echo $table->render([$detailsData]);

            //Description Table
            $table = DataTable::createDetails('description');
            $table->setTitle(__('Description'));

            //TODO: Can this be simplified?
            if (!$hasTechAssigned) {
                 if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'acceptIssue') && !$isPersonsIssue) {
                    $table->addHeaderAction('accept', __('Accept'))
                            ->setIcon('page_new')
                            ->directLink()
                            ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_acceptProcess.php')
                            ->addParam('issueID', $issueID);
                }
                if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'assignIssue') && (!$isPersonsIssue || $hasFullAccess)) {
                    $table->addHeaderAction('assign', __('Assign'))
                            ->setIcon('attendance')
                            ->modalWindow()
                            ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_assign.php')
                            ->addParam('issueID', $issueID);
                }
                if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'resolveIssue') || $isPersonsIssue) {
                    $table->addHeaderAction('resolve', __('Resolve'))
                            ->setIcon('iconTick')
                            ->directLink()
                            ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_resolveProcess.php')
                            ->addParam('issueID', $issueID);
                }
            }

            $table->addColumn('description')
                    ->width('100%');

            echo $table->render([$issue]);

            if ($hasTechAssigned) {
                $IssueDiscussGateway = $container->get(IssueDiscussGateway::class);
                $logs = $IssueDiscussGateway->getIssueDiscussionByID($issueID)->fetchAll();

                echo $page->fetchFromTemplate('ui/discussion.twig.html', [
                    'title' => __('Comments'),
                    'discussion' => $logs
                ]); 
                
                //Again a bit of a cheat, we'll see how this goes.
                $headerActions = array();

                if ($issue['status'] != 'Resolved') {
                    $action = new Action('refresh', __('Refresh'));
                    $action->setIcon('refresh')
                            ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_discussView.php')
                            ->addParam('issueID', $issueID);
                    $headerActions[] = $action;

                    $action = new Action('add', __('Add'));
                    $action->modalWindow()
                            ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_discussPost.php')
                            ->addParam('issueID', $issueID);
                    
                    $headerActions[] = $action;
                    
                    if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'reassignIssue') && (!$isPersonsIssue || $hasFullAccess)) {
                        $action = new Action('reassign', __('Reassign'));
                        $action->setIcon('attendance')
                                ->modalWindow()
                                ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_assign.php')
                                ->addParam('issueID', $issueID);
                    }
                    $headerActions[] = $action;
                    
                    if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'resolveIssue') || $isPersonsIssue) {
                        $action = new Action('resolve', __('Resolve'));
                        $action->setIcon('iconTick')
                                ->directLink()
                                ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_resolveProcess.php')
                                ->addParam('issueID', $issueID);

                        $headerActions[] = $action;
                    }
                } else {
                     if ($techGroupGateway->getPermissionValue($gibbon->session->get('gibbonPersonID'), 'reincarnateIssue') || $isPersonsIssue) {
                        $action = new Action('reincarnate', __('Reincarnate'));
                        $action->directLink()
                                ->setURL('/modules/' . $gibbon->session->get('module') . '/issues_reincarnateProcess.php')
                                ->setIcon('reincarnate')
                                ->addParam('issueID', $issueID);

                        $headerActions[] = $action;
                    }
                }

                echo '<div class="linkTop">';
                    foreach ($headerActions as $action) {
                        echo $action->getOutput();
                    }
                echo '</div>';
            }
        } else {
            $page->addError(__('You do not have access to this action.'));
        }
    }
}
?>
