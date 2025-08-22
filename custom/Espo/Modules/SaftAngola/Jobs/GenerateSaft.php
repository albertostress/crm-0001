<?php

namespace Espo\Modules\SaftAngola\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Di;

class GenerateSaft implements 
    JobDataLess,
    Di\EntityManagerAware,
    Di\ServiceFactoryAware
{
    use Di\EntityManagerSetter;
    use Di\ServiceFactorySetter;

    public function run(): void
    {
        // Find SAFT exports that are in Processing status
        $saftExports = $this->entityManager->getRepository('SaftExport')->find([
            'whereClause' => [
                'status' => 'Processing'
            ],
            'orderBy' => 'createdAt',
            'order' => 'ASC'
        ]);

        foreach ($saftExports as $saftExport) {
            try {
                $this->processSaftExport($saftExport);
            } catch (\Exception $e) {
                // Log error and mark export as failed
                $this->entityManager->saveEntity($saftExport, [
                    'status' => 'Failed',
                    'validationErrors' => $e->getMessage()
                ]);
            }
        }
    }

    private function processSaftExport($saftExport): void
    {
        $saftConfigId = $saftExport->get('saftConfigId');
        $periodStart = $saftExport->get('periodStart');
        $periodEnd = $saftExport->get('periodEnd');

        if (!$saftConfigId || !$periodStart || !$periodEnd) {
            throw new \Exception('Missing required data for SAFT generation');
        }

        // Generate SAFT XML
        $saftGeneratorService = $this->serviceFactory->create('SaftGenerator');
        $result = $saftGeneratorService->generateSaftXml($saftConfigId, $periodStart, $periodEnd);

        // Update SaftExport record with results
        $updateData = [
            'status' => $result['success'] ? 'Success' : 'Failed',
            'fileSize' => $result['fileSize'],
            'totalInvoices' => $result['totalInvoices'],
            'totalAccounts' => $result['totalAccounts'],
            'totalProducts' => $result['totalProducts'],
            'filePath' => $result['filePath'],
            'validationErrors' => $result['validationErrors']
        ];

        if ($result['success']) {
            $updateData['xmlContent'] = $result['xmlContent'];
        }

        $this->entityManager->saveEntity($saftExport, $updateData);
    }
}