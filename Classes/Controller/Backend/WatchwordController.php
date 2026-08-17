<?php

declare(strict_types=1);

namespace Emh\Watchword\Controller\Backend;

use Emh\Watchword\Domain\Repository\WatchwordRepository;
use Emh\Watchword\Service\WatchwordImportService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class WatchwordController extends ActionController
{
    private const ITEMS_PER_PAGE = 50;
    private const TOKEN_PATTERN = '/^[a-f0-9]{32}\.(xlsx|xls|csv)$/';
    private const EXT_KEY = 'watchword';

    protected ModuleTemplate $moduleTemplate;

    public function __construct(
        private readonly WatchwordRepository $watchwordRepository,
        private readonly WatchwordImportService $importService,
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly IconFactory $iconFactory,
        private readonly ExtensionConfiguration $extensionConfiguration,
        private readonly BackendUriBuilder $backendUriBuilder,
    ) {}

    public function initializeAction(): void
    {
        $this->moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $this->moduleTemplate->setFlashMessageQueue($this->getFlashMessageQueue());
    }

    public function indexAction(int $year = 0, int $currentPage = 1): ResponseInterface
    {
        $this->addImportButton();

        $watchwordEntities = $this->watchwordRepository->findAllByYear($year > 0 ? $year : null)->toArray();

        $rows = [];
        foreach ($watchwordEntities as $watchword) {
            $rows[] = [
                'uid' => $watchword->getUid(),
                'date' => $watchword->getDate(),
                'weekday' => $watchword->getWeekday(),
                'sundayName' => $watchword->getSundayName(),
                'watchwordVerse' => $watchword->getWatchwordVerse(),
                'watchwordText' => $watchword->getWatchwordText(),
                'teachingVerse' => $watchword->getTeachingVerse(),
                'teachingText' => $watchword->getTeachingText(),
                'editUrl' => $this->getRecordEditUrl((int)$watchword->getUid()),
                'deleteUrl' => $this->uriBuilder->setArguments([
                    'controller' => 'Backend\Watchword',
                    'action' => 'delete',
                    'uid' => $watchword->getUid(),
                ])->buildBackendUri(),
            ];
        }

        $paginator = new ArrayPaginator($rows, $currentPage, self::ITEMS_PER_PAGE);
        $pagination = new SimplePagination($paginator);

        $years = $this->watchwordRepository->findDistinctYears();

        $this->moduleTemplate->assignMultiple([
            'paginator' => $paginator,
            'pagination' => $pagination,
            'years' => array_combine($years, $years),
            'currentYear' => $year,
        ]);

        return $this->moduleTemplate->renderResponse('Index');
    }

    public function deleteAction(): ResponseInterface
    {
        $uid = (int)($this->request->getQueryParams()['uid'] ?? 0);
        if ($uid > 0) {
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->start([], [
                WatchwordRepository::TABLE => [
                    $uid => ['delete' => 1],
                ],
            ]);
            $dataHandler->process_cmdmap();
        }

        return $this->redirect('index');
    }

    public function importAction(): ResponseInterface
    {
        $this->moduleTemplate->assignMultiple([
            'importUrl' => $this->uriBuilder->setArguments(['controller' => 'Backend\Watchword', 'action' => 'importPreview'])->buildBackendUri(),
            'allowedExtensions' => $this->importService->getAllowedExtensions(),
        ]);

        return $this->moduleTemplate->renderResponse('Import');
    }

    public function importPreviewAction(): ResponseInterface
    {
        $uploadedFiles = $this->request->getUploadedFiles();
        $uploadedFile = $uploadedFiles['file'] ?? null;

        if ($uploadedFile === null || $uploadedFile->getError() !== \UPLOAD_ERR_OK || $uploadedFile->getSize() === 0) {
            $this->addTranslatedFlashMessage('module.import.error.no_file', severity: ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        $extension = strtolower(pathinfo($uploadedFile->getClientFilename() ?? '', PATHINFO_EXTENSION));
        if (!$this->importService->isAllowedExtension($extension)) {
            $this->addTranslatedFlashMessage('module.import.error.extension', severity: ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        $fileToken = bin2hex(random_bytes(16)) . '.' . $extension;
        $targetPath = $this->importService->getTransientDirectory() . $fileToken;
        $uploadedFile->moveTo($targetPath);

        $result = $this->importService->parseAndValidate($targetPath);

        if (!$result['headerValid']) {
            $this->importService->deleteTempFile($targetPath);
            $message = LocalizationUtility::translate('module.import.error.headers', self::EXT_KEY) ?? '';
            if ($result['headerError'] !== '') {
                $message .= ' (' . $result['headerError'] . ')';
            }
            $this->addFlashMessage($message, '', ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        $this->moduleTemplate->assignMultiple([
            'fileToken' => $fileToken,
            'newCount' => count($result['newRows']),
            'duplicateCount' => $result['duplicateCount'],
            'invalidRows' => $result['invalidRows'],
            'invalidCount' => count($result['invalidRows']),
            'confirmUrl' => $this->uriBuilder->setArguments(['controller' => 'Backend\Watchword', 'action' => 'importConfirm', 'fileToken' => $fileToken])->buildBackendUri(),
        ]);

        return $this->moduleTemplate->renderResponse('ImportPreview');
    }

    public function importConfirmAction(string $fileToken = ''): ResponseInterface
    {
        if (preg_match(self::TOKEN_PATTERN, $fileToken) !== 1) {
            $this->addTranslatedFlashMessage('module.import.error.token', severity: ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        $filePath = $this->importService->getTransientDirectory() . $fileToken;
        if (!is_file($filePath)) {
            $this->addTranslatedFlashMessage('module.import.error.token', severity: ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        // Re-validate against the current database state here, never trust the browser round-trip
        // between preview and confirm for the actual write.
        $result = $this->importService->parseAndValidate($filePath);

        if (!$result['headerValid']) {
            $this->importService->deleteTempFile($filePath);
            $this->addTranslatedFlashMessage('module.import.error.headers', severity: ContextualFeedbackSeverity::ERROR);

            return $this->redirect('import');
        }

        $storagePid = (int)$this->extensionConfiguration->get(self::EXT_KEY, 'storagePid');
        $insertedCount = $this->importService->insert($result['newRows'], $storagePid, $this->getBackendUserId());

        $this->importService->deleteTempFile($filePath);

        $this->addTranslatedFlashMessage(
            'module.confirm.success',
            [$insertedCount, $result['duplicateCount'], count($result['invalidRows'])],
            ContextualFeedbackSeverity::OK
        );

        return $this->redirect('index');
    }

    private function addImportButton(): void
    {
        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $importUrl = $this->uriBuilder->setArguments(['controller' => 'Backend\Watchword', 'action' => 'import'])->buildBackendUri();

        $importButton = $buttonBar->makeLinkButton()
            ->setHref($importUrl)
            ->setIcon($this->iconFactory->getIcon('actions-upload', $this->smallIconSize()))
            ->setShowLabelText(true)
            ->setTitle(LocalizationUtility::translate('module.index.import_button', self::EXT_KEY) ?? '');
        $buttonBar->addButton($importButton, ButtonBar::BUTTON_POSITION_LEFT, 1);
    }

    /**
     * TYPO3 v12 has no IconSize enum (only the Icon::SIZE_* string constants), while TYPO3 v14
     * removed those constants entirely in favor of the enum. v13 accepts either.
     */
    private function smallIconSize(): string|\TYPO3\CMS\Core\Imaging\IconSize
    {
        return class_exists(\TYPO3\CMS\Core\Imaging\IconSize::class)
            ? \TYPO3\CMS\Core\Imaging\IconSize::SMALL
            : Icon::SIZE_SMALL;
    }

    private function addTranslatedFlashMessage(string $key, ?array $arguments = null, ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::INFO): void
    {
        $this->addFlashMessage(
            LocalizationUtility::translate($key, self::EXT_KEY, $arguments) ?? '',
            '',
            $severity
        );
    }

    private function getRecordEditUrl(int $uid): string
    {
        return (string)$this->backendUriBuilder->buildUriFromRoute('record_edit', [
            'edit' => [
                WatchwordRepository::TABLE => [
                    $uid => 'edit',
                ],
            ],
            'returnUrl' => $this->uriBuilder->setArguments(['controller' => 'Backend\Watchword', 'action' => 'index'])->buildBackendUri(),
        ]);
    }

    private function getBackendUserId(): int
    {
        return (int)($this->getBackendUser()->user['uid'] ?? 0);
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }
}
