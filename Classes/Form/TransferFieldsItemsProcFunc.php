<?php

declare(strict_types=1);

namespace Gedankenfolger\GedankenfolgerPowermailRedirect\Form;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * TCA itemsProcFunc for the "Fields to transfer" FlexForm select.
 *
 * When a Powermail form is already selected on the content element (pi_flexform),
 * only the fields belonging to that form are returned. If no form has been
 * selected yet, all non-submit Powermail fields are listed as a fallback.
 *
 * Each item label is shown as "Field title [marker]" to help editors identify
 * the correct field when multiple forms share similar titles.
 */
class TransferFieldsItemsProcFunc
{
    /**
     * Populates $params['items'] with selectable Powermail field records.
     *
     * Called by TYPO3 as a TCA itemsProcFunc. Reads the Powermail form UID
     * from the content element's pi_flexform and restricts the list to fields
     * of that form only (joined via tx_powermail_domain_model_page).
     *
     * @param array<string, mixed> $params TCA itemsProcFunc parameter bag (passed by reference)
     */
    public function getItems(array &$params): void
    {
        $formUid = $this->resolveFormUid($params['row']['pi_flexform'] ?? '');

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_powermail_domain_model_field');

        // Remove default restrictions so we can apply them explicitly for both
        // the main table and the joined page table.
        $queryBuilder->getRestrictions()->removeAll();

        $queryBuilder
            ->select('f.uid', 'f.title', 'f.marker')
            ->from('tx_powermail_domain_model_field', 'f')
            ->join('f', 'tx_powermail_domain_model_page', 'p', 'f.page = p.uid')
            ->where(
                $queryBuilder->expr()->eq('f.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('f.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->neq('f.type', $queryBuilder->createNamedParameter('submit')),
                $queryBuilder->expr()->eq('p.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('p.hidden', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            )
            ->orderBy('f.title', 'ASC');

        if ($formUid > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->eq(
                    'p.form',
                    $queryBuilder->createNamedParameter($formUid, Connection::PARAM_INT)
                )
            );
        }

        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $label = $row['title'];
            if ((string)$row['marker'] !== '') {
                $label .= ' [' . $row['marker'] . ']';
            }
            $params['items'][] = [
                'label' => $label,
                'value' => (int)$row['uid'],
            ];
        }
    }

    /**
     * Extracts the selected Powermail form UID from the pi_flexform XML.
     *
     * Parses the raw FlexForm XML stored in the pi_flexform column of tt_content
     * and reads the form UID from the exact path used by Powermail:
     * data → sDEF → lDEF → settings.flexform.main.form → vDEF
     *
     * @param mixed $rawFlexForm Raw XML string from the pi_flexform column
     * @return int Positive form UID, or 0 if not available or not yet selected
     */
    private function resolveFormUid(mixed $rawFlexForm): int
    {
        if (!is_string($rawFlexForm) || $rawFlexForm === '') {
            return 0;
        }

        $flexFormArray = GeneralUtility::xml2array($rawFlexForm);
        if (!is_array($flexFormArray)) {
            return 0;
        }

        // Powermail stores the selected form UID in sheet sDEF at this exact path
        return (int)($flexFormArray['data']['sDEF']['lDEF']['settings.flexform.main.form']['vDEF'] ?? 0);
    }
}
