<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2020 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 */

namespace App\Controller;

use App\DataTables\LogDataTable;
use App\Entity\Base\AbstractDBElement;
use App\Entity\LogSystem\AbstractLogEntry;
use App\Entity\LogSystem\CollectionElementDeleted;
use App\Entity\LogSystem\ElementCreatedLogEntry;
use App\Entity\LogSystem\ElementDeletedLogEntry;
use App\Entity\LogSystem\ElementEditedLogEntry;
use App\Services\LogSystem\EventUndoHelper;
use App\Services\LogSystem\TimeTravel;
use Doctrine\ORM\EntityManagerInterface;
use Omines\DataTablesBundle\DataTableFactory;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/log")
 */
class LogController extends AbstractController
{
    protected $entityManager;
    protected $timeTravel;
    protected $dbRepository;

    public function __construct(EntityManagerInterface $entityManager, TimeTravel $timeTravel)
    {
        $this->entityManager = $entityManager;
        $this->timeTravel = $timeTravel;
        $this->dbRepository = $entityManager->getRepository(AbstractDBElement::class);
    }

    /**
     * @Route("/", name="log_view")
     *
     * @return JsonResponse|Response
     */
    public function showLogs(Request $request, DataTableFactory $dataTable)
    {
        $this->denyAccessUnlessGranted('@system.show_logs');

        $table = $dataTable->createFromType(LogDataTable::class)
            ->handleRequest($request);

        if ($table->isCallback()) {
            return $table->getResponse();
        }

        return $this->render('LogSystem/log_list.html.twig', [
            'datatable' => $table,
        ]);
    }

    /**
     * @Route("/undo", name="log_undo", methods={"POST"})
     */
    public function undoRevertLog(Request $request, EventUndoHelper $eventUndoHelper): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        $mode = EventUndoHelper::MODE_UNDO;
        $id = $request->request->get('undo');

        //If no undo value was set check if a revert was set
        if (null === $id) {
            $id = $request->get('revert');
            $mode = EventUndoHelper::MODE_REVERT;
        }

        $log_element = $this->entityManager->find(AbstractLogEntry::class, $id);
        if (null === $log_element) {
            throw new \InvalidArgumentException('No log entry with the given ID is existing!');
        }

        $this->denyAccessUnlessGranted('revert_element', $log_element->getTargetClass());

        $eventUndoHelper->setMode($mode);
        $eventUndoHelper->setUndoneEvent($log_element);

        if (EventUndoHelper::MODE_UNDO === $mode) {
            $this->undoLog($log_element);
        } elseif (EventUndoHelper::MODE_REVERT === $mode) {
            $this->revertLog($log_element);
        }

        $eventUndoHelper->clearUndoneEvent();

        $redirect = $request->request->get('redirect_back');

        return $this->redirect($redirect);
    }

    protected function revertLog(AbstractLogEntry $logEntry): void
    {
        $timestamp = $logEntry->getTimestamp();
        $element = $this->entityManager->find($logEntry->getTargetClass(), $logEntry->getTargetID());
        //If the element is not available in DB try to undelete it
        if (null === $element) {
            $element = $this->timeTravel->undeleteEntity($logEntry->getTargetClass(), $logEntry->getTargetID());
            $this->entityManager->persist($element);
            $this->entityManager->flush();
            $this->dbRepository->changeID($element, $logEntry->getTargetID());
        }

        if (! $element instanceof AbstractDBElement) {
            $this->addFlash('error', 'log.undo.target_not_found');

            return;
        }

        $this->timeTravel->revertEntityToTimestamp($element, $timestamp);
        $this->entityManager->flush();
        $this->addFlash('success', 'log.undo.revert_success');
    }

    protected function undoLog(AbstractLogEntry $log_element): void
    {
        if ($log_element instanceof ElementDeletedLogEntry || $log_element instanceof CollectionElementDeleted) {
            if ($log_element instanceof ElementDeletedLogEntry) {
                $element_class = $log_element->getTargetClass();
                $element_id = $log_element->getTargetID();
            } else {
                $element_class = $log_element->getDeletedElementClass();
                $element_id = $log_element->getDeletedElementID();
            }

            //Check if the element we want to undelete already exits
            if (null === $this->entityManager->find($element_class, $element_id)) {
                $undeleted_element = $this->timeTravel->undeleteEntity($element_class, $element_id);
                $this->entityManager->persist($undeleted_element);
                $this->entityManager->flush();
                $this->dbRepository->changeID($undeleted_element, $element_id);
                $this->addFlash('success', 'log.undo.element_undelete_success');
            } else {
                $this->addFlash('warning', 'log.undo.element_element_already_undeleted');
            }
        } elseif ($log_element instanceof ElementCreatedLogEntry) {
            $element = $this->entityManager->find($log_element->getTargetClass(), $log_element->getTargetID());
            if (null !== $element) {
                $this->entityManager->remove($element);
                $this->entityManager->flush();
                $this->addFlash('success', 'log.undo.element_delete_success');
            } else {
                $this->addFlash('warning', 'log.undo.element.element_already_delted');
            }
        } elseif ($log_element instanceof ElementEditedLogEntry) {
            $element = $this->entityManager->find($log_element->getTargetClass(), $log_element->getTargetID());
            if ($element instanceof AbstractDBElement) {
                $this->timeTravel->applyEntry($element, $log_element);
                $this->entityManager->flush();
                $this->addFlash('success', 'log.undo.element_change_undone');
            } else {
                $this->addFlash('error', 'log.undo.do_undelete_before');
            }
        } else {
            $this->addFlash('error', 'log.undo.log_type_invalid');
        }
    }
}
