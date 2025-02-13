<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

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
use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\HelpDesk\Domain\ReplyTemplateGateway;
use Gibbon\Module\HelpDesk\Domain\IssueDiscussGateway;
use Gibbon\Module\HelpDesk\Domain\IssueGateway;
use Gibbon\Module\HelpDesk\Domain\IssueNoteGateway;
use Gibbon\Module\HelpDesk\Domain\TechGroupGateway;
use Gibbon\Module\HelpDesk\Domain\TechnicianGateway;
use Gibbon\Domain\DataSet;
use Gibbon\Domain\System\DiscussionGateway;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\UserGateway;
use Gibbon\Domain\School\FacilityGateway;
use Gibbon\View\View;

$page->breadcrumbs->add(__('Discuss Issue'));

if (!isModuleAccessible($guid, $connection2)) {
    //Acess denied
    $page->addError(__('You do not have access to this action.'));
} else {
    $issueID = $_GET['issueID'] ?? '';

    $issueGateway = $container->get(IssueGateway::class);
    $issue = $issueGateway->getIssueByID($issueID);

    if (empty($issueID) || empty($issue)) {
        $page->addError(__('No Issue Selected.'));
    } else {
        //Set up gateways
        $techGroupGateway = $container->get(TechGroupGateway::class);
        $technicianGateway = $container->get(TechnicianGateway::class);

        //Information about the current user
        $gibbonPersonID = $session->get('gibbonPersonID');
        $isPersonsIssue = ($issue['gibbonPersonID'] == $gibbonPersonID);
        $isTechnician = $technicianGateway->getTechnicianByPersonID($gibbonPersonID)->isNotEmpty();
        $isRelated = $issueGateway->isRelated($issueID, $gibbonPersonID);
        $hasViewAccess = $techGroupGateway->getPermissionValue($gibbonPersonID, 'viewIssue');
        $hasFullAccess = $techGroupGateway->getPermissionValue($gibbonPersonID, 'fullAccess');

        //Information about the issue's technician
        $technician = $technicianGateway->getTechnician($issue['technicianID']);
        $technician = $technician->isNotEmpty() ? $technician->fetch() : [];
        $hasTechAssigned = !empty($technician);
        $isResolved = ($issue['status'] == 'Resolved');

        $allowed = $isRelated
            || (!$hasTechAssigned && $isTechnician)
            || $hasViewAccess;


        if ($allowed) {
            $createdByShow = ($issue['createdByID'] != $issue['gibbonPersonID']);

            $userGateway = $container->get(UserGateway::class);
            $owner = $userGateway->getByID($issue['gibbonPersonID']);
            if ($owner['gibbonRoleIDPrimary'] == '003' ) {
                $ownerRole = 'Student';
            } else {
                $ownerRole = 'Staff';
            }
            $detailsData = [
                'issueID' => $issueID,
                'owner' => Format::nameLinked($owner['gibbonPersonID'], $owner['title'] , $owner['preferredName'] , $owner['surname'] , $ownerRole),
                'technician' => $hasTechAssigned ? Format::name($technician['title'] , $technician['preferredName'] , $technician['surname'] , 'Student') : __('Unassigned'),
                'date' => Format::date($issue['date']),
            ];

            $table = DataTable::createDetails('details');
            $table->setTitle($issue['issueName']);
            $table->addMetaData('allowHTML', ['description']);

            if ($isResolved) {
                if ($isPersonsIssue || ($isRelated && $techGroupGateway->getPermissionValue($gibbonPersonID, 'reincarnateIssue')) || $hasFullAccess) {
                    $table->addHeaderAction('reincarnate', __('Reincarnate'))
                            ->setIcon('reincarnate')
                            ->directLink()
                            ->setURL('/modules/' . $session->get('module') . '/issues_reincarnateProcess.php')
                            ->addParam('issueID', $issueID);
                }
            } else {
                if (!$hasTechAssigned) {
                     if ($techGroupGateway->getPermissionValue($gibbonPersonID, 'acceptIssue') && !$isPersonsIssue) {
                        $table->addHeaderAction('accept', __('Accept'))
                                ->setIcon('page_new')
                                ->directLink()
                                ->setURL('/modules/' . $session->get('module') . '/issues_acceptProcess.php')
                                ->addParam('issueID', $issueID);
                    }
                    if (($techGroupGateway->getPermissionValue($gibbonPersonID, 'assignIssue') && !$isPersonsIssue) || $hasFullAccess) {
                        $table->addHeaderAction('assign', __('Assign'))
                                ->setIcon('attendance')
                                ->modalWindow()
                                ->setURL('/modules/' . $session->get('module') . '/issues_assign.php')
                                ->addParam('issueID', $issueID);
                    }
                } else {
                    if (($techGroupGateway->getPermissionValue($gibbonPersonID, 'reassignIssue') && !$isPersonsIssue) || $hasFullAccess) {
                        $table->addHeaderAction('reassign', __('Reassign'))
                                ->setIcon('attendance')
                                ->modalWindow()
                                ->setURL('/modules/' . $session->get('module') . '/issues_assign.php')
                                ->addParam('issueID', $issueID);
                    }
                }

                if ($isPersonsIssue || ($isRelated && $techGroupGateway->getPermissionValue($gibbonPersonID, 'resolveIssue')) || $hasFullAccess) {
                    $table->addHeaderAction('resolve', __('Resolve'))
                            ->setIcon('iconTick')
                            ->directLink()
                            ->setURL('/modules/' . $session->get('module') . '/issues_resolveProcess.php')
                            ->addParam('issueID', $issueID);
                }
            }

            $table->addColumn('issueID', __('ID'))
                    ->format(Format::using('number', ['issueID', 0]));

            $table->addColumn('owner', __('Owner'));

            $table->addColumn('technician', __('Technician'));

            $table->addColumn('date', __('Date'));

            if (!empty($issue['facility'])) {
                $detailsData['facility'] = $issue['facility'];
                $table->addColumn('facility', __('Facility'));
            }
            if ($createdByShow) {
                $createdBy = $userGateway->getByID($issue['createdByID']);
                $detailsData['createdBy'] = Format::name($createdBy['title'] , $createdBy['preferredName'] , $createdBy['surname'] , 'Student');
                $table->addColumn('createdBy', __('Created By'));
            }

            $table->addMetaData('gridClass', 'grid-cols-' . count($detailsData));

            $detailsData['description'] = $issue['description'];
            $table->addColumn('description', __('Description'))->addClass('col-span-10');

            echo $table->render([$detailsData]);

            $settingGateway = $container->get(SettingGateway::class);

            if ($isTechnician && !$isPersonsIssue && $settingGateway->getSettingByScope('Help Desk', 'techNotes')) {
                $form = Form::create('techNotes',  $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/issues_discussNoteProccess.php', 'post');
                $form->setAttribute('x-data', '{comments: false, invalid: false, submitting: false}');
                $form->addHiddenValue('issueID', $issueID);
                $form->addHiddenValue('address', $session->get('address'));

                $row = $form->addRow();
                    $col = $row->addColumn();
                        $col->addHeading(__('Technician Notes'))->addClass('inline-block');

                    $col->addButton(__('Add Technician Note'))->setIcon('add')->addClass('float-right')->setAttribute('@click', 'comments = !comments');

                $row = $form->addRow()->setClass('flex flex-col sm:flex-row items-stretch sm:items-center')->setAttribute('x-cloak')->setAttribute('x-show', 'comments');
                    $col = $row->addColumn();
                        $col->addLabel('techNote', __('Technician Note'));
                        $col->addEditor('techNote', $guid)
                            ->setRows(5)
                            ->showMedia()
                            ->required();
                        $col->addSubmit();

                $issueNoteGateway = $container->get(IssueNoteGateway::class);
                $notes = $issueNoteGateway->getIssueNotesByID($issueID)->fetchAll();

                if (count($notes) > 0) {
                    $form->addRow()
                        ->addContent('comments')
                        ->setContent($page->fetchFromTemplate('ui/discussion.twig.html', [
                            'title' => __(''),
                            'discussion' => $notes
                        ]));
                }

                echo $form->getOutput();
            }


            $form = Form::create('issueDiscuss',  $session->get('absoluteURL') . '/modules/' . $session->get('module') . '/issues_discussPostProccess.php?issueID=' . $issueID, 'post');
            $form->setAttribute('x-data', '{comments: false, invalid: false, submitting: false}');
            $form->addHiddenValue('address', $session->get('address'));
            $row = $form->addRow();
            $col = $row->addColumn();
                $col->addHeading(__('Comments'))->addClass('inline-block');

            if ($issue['status'] == 'Pending' && ($isRelated || $hasFullAccess)) {
                $col->addButton(__('Add Comment'))->setIcon('add')->addClass('float-right')->setAttribute('@click', 'comments = !comments');
                
                if ($isTechnician) {
                    $replyTemplateGateway = $container->get(ReplyTemplateGateway::class);
                    $criteria = $replyTemplateGateway->newQueryCriteria()
                        ->sortBy(['name', 'helpDeskReplyTemplateID']);
                    $templateNames = NULL;
                    $templates = NULL;
                    $replyTemplates = $replyTemplateGateway->queryTemplates($criteria);
                    foreach ($replyTemplates as $replyTemplate) {
                        $templateNames[$replyTemplate['helpDeskReplyTemplateID']] = $replyTemplate['name'];
                        $templates[$replyTemplate['helpDeskReplyTemplateID']] = $replyTemplate['body'];
                    }
                    if ($templates != NULL) {
                        $row = $form->addRow()->setClass('flex flex-col sm:flex-row items-stretch sm:items-center')->setAttribute('x-cloak')->setAttribute('x-show', 'comments');
                            $row->addLabel('replyTemplates', __('Reply Templates'));
                            $row->addSelect('replyTemplates')
                                ->fromArray($templateNames)->placeholder('Select a Reply Template');
                    }
                }
                $row = $form->addRow()->setClass('flex flex-col sm:flex-row items-stretch sm:items-center')->setAttribute('x-cloak')->setAttribute('x-show', 'comments');
                    $column = $row->addColumn();
                    $column->addLabel('comment', __('Comment'));
                    $column->addEditor('comment', $guid)
                        ->setRows(5)
                        ->showMedia()
                        ->required();
                    $column->addSubmit();
               
            }

            $issueDiscussGateway = $container->get(IssueDiscussGateway::class);
            $logs = $issueDiscussGateway->getIssueDiscussionByID($issueID)->fetchAll();

            if (count($logs) > 0) {
                array_walk($logs, function (&$discussion, $key) use ($issue) {
                    if ($discussion['gibbonPersonID'] == $issue['gibbonPersonID']) {
                        $discussion['type'] = 'Owner';
                    } else {
                        $discussion['type'] = 'Technician';
                    }
                });

                $form->addRow()
                    ->addContent('comments')
                    ->setContent($page->fetchFromTemplate('ui/discussion.twig.html', [
                        'title' => __(''),
                        'discussion' => $logs
                    ]));
            }

            if (count($form->getRows()) > 1) {
                echo $form->getOutput();
            }
            if ($isTechnician) {
                ?>
                <script>
                //Javascript to change reply when template selector is changed.
                    <?php echo 'var templates = ' . json_encode($templates) . ';'; ?>
                    $("select[name=replyTemplates]").on('change', function(){
                        var templateID = $(this).val();
                        if (templateID != '' && templateID >= 0) {
                            if(confirm('Are you sure you want to use this template. Warning: This will overwrite any thing currently written.')) {
                                tinyMCE.get('comment').setContent(templates[templateID]);
                            }
                        }
                    });
                </script>
                <?php
            } 
        } else {
            $page->addError(__('You do not have access to this action.'));
        }
    }
        
}
