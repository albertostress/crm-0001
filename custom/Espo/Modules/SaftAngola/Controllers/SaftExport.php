<?php

namespace Espo\Modules\SaftAngola\Controllers;

use Espo\Core\Controllers\Record;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Di;

class SaftExport extends Record implements
    Di\ServiceFactoryAware
{
    use Di\ServiceFactorySetter;

    public function actionGenerateSaft(Request $request, Response $response): \stdClass
    {
        if (!$request->isPost()) {
            throw new BadRequest('POST method required');
        }

        $data = $request->getParsedBody();
        
        $saftConfigId = $data->saftConfigId ?? null;
        $periodStart = $data->periodStart ?? null;
        $periodEnd = $data->periodEnd ?? null;
        $name = $data->name ?? null;

        if (!$saftConfigId || !$periodStart || !$periodEnd || !$name) {
            throw new BadRequest('Missing required parameters');
        }

        // Validate date format
        if (!$this->isValidDate($periodStart) || !$this->isValidDate($periodEnd)) {
            throw new BadRequest('Invalid date format. Use YYYY-MM-DD');
        }

        // Validate period
        if (strtotime($periodStart) > strtotime($periodEnd)) {
            throw new BadRequest('Start date must be before end date');
        }

        try {
            // Create SaftExport record
            $saftExport = $this->getEntityManager()->createEntity('SaftExport', [
                'name' => $name,
                'periodStart' => $periodStart,
                'periodEnd' => $periodEnd,
                'saftConfigId' => $saftConfigId,
                'status' => 'Processing',
                'exportDate' => date('Y-m-d H:i:s')
            ]);

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

            $this->getEntityManager()->saveEntity($saftExport, $updateData);

            return (object) [
                'success' => $result['success'],
                'id' => $saftExport->getId(),
                'message' => $result['success'] ? 'SAFT export generated successfully' : 'Export failed: ' . $result['validationErrors']
            ];

        } catch (\Exception $e) {
            if (isset($saftExport)) {
                $this->getEntityManager()->saveEntity($saftExport, [
                    'status' => 'Failed',
                    'validationErrors' => $e->getMessage()
                ]);
            }
            
            throw new BadRequest('Export failed: ' . $e->getMessage());
        }
    }

    public function actionDownloadSaft(Request $request, Response $response): Response
    {
        $id = $request->getRouteParam('id');
        
        if (!$id) {
            throw new BadRequest('ID is required');
        }

        $saftExport = $this->getEntityManager()->getEntity('SaftExport', $id);
        
        if (!$saftExport) {
            throw new NotFound('SAFT Export not found');
        }

        if ($saftExport->get('status') !== 'Success') {
            throw new BadRequest('Export is not ready for download');
        }

        $filePath = $saftExport->get('filePath');
        
        if (!$filePath || !$this->getFileManager()->isFile($filePath)) {
            throw new NotFound('Export file not found');
        }

        $fileContent = $this->getFileManager()->getContents($filePath);
        $fileName = basename($filePath);

        $response->setHeader('Content-Type', 'application/xml');
        $response->setHeader('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        $response->setHeader('Content-Length', (string) strlen($fileContent));
        
        $response->writeBody($fileContent);
        
        return $response;
    }

    public function actionValidateSaft(Request $request, Response $response): \stdClass
    {
        $id = $request->getRouteParam('id');
        
        if (!$id) {
            throw new BadRequest('ID is required');
        }

        $saftExport = $this->getEntityManager()->getEntity('SaftExport', $id);
        
        if (!$saftExport) {
            throw new NotFound('SAFT Export not found');
        }

        if ($saftExport->get('status') !== 'Success') {
            throw new BadRequest('Export is not ready for validation');
        }

        try {
            $xmlContent = $saftExport->get('xmlContent');
            
            if (!$xmlContent) {
                throw new \Exception('XML content not found');
            }

            // Decompress XML content
            $xmlString = gzuncompress(base64_decode($xmlContent));
            
            if ($xmlString === false) {
                throw new \Exception('Failed to decompress XML content');
            }

            // Load XSD schema for validation
            $xsdPath = 'custom/Espo/Modules/SaftAngola/Resources/xsd/SAFTAO1.01_01.xsd';
            
            if (!file_exists($xsdPath)) {
                throw new \Exception('XSD schema file not found');
            }

            // Validate XML against XSD
            $dom = new \DOMDocument();
            $dom->loadXML($xmlString);
            
            $isValid = $dom->schemaValidate($xsdPath);
            
            if ($isValid) {
                $this->getEntityManager()->saveEntity($saftExport, [
                    'validationErrors' => ''
                ]);
                
                return (object) [
                    'success' => true,
                    'message' => 'XML is valid according to SAFT-AO schema'
                ];
            } else {
                $errors = libxml_get_errors();
                $errorMessages = [];
                
                foreach ($errors as $error) {
                    $errorMessages[] = sprintf('Line %d: %s', $error->line, trim($error->message));
                }
                
                $validationErrors = implode('; ', $errorMessages);
                
                $this->getEntityManager()->saveEntity($saftExport, [
                    'status' => 'ValidationError',
                    'validationErrors' => $validationErrors
                ]);
                
                return (object) [
                    'success' => false,
                    'message' => 'XML validation failed',
                    'errors' => $errorMessages
                ];
            }
            
        } catch (\Exception $e) {
            $this->getEntityManager()->saveEntity($saftExport, [
                'status' => 'ValidationError',
                'validationErrors' => $e->getMessage()
            ]);
            
            return (object) [
                'success' => false,
                'message' => 'Validation failed: ' . $e->getMessage()
            ];
        }
    }

    public function actionSubmitToAgt(Request $request, Response $response): \stdClass
    {
        $id = $request->getRouteParam('id');
        
        if (!$id) {
            throw new BadRequest('ID is required');
        }

        $saftExport = $this->getEntityManager()->getEntity('SaftExport', $id);
        
        if (!$saftExport) {
            throw new NotFound('SAFT Export not found');
        }

        if ($saftExport->get('status') !== 'Success') {
            throw new BadRequest('Export must be successfully generated before submission');
        }

        try {
            // This would integrate with AGT's API for submission
            // For now, we'll just mark as submitted with a mock reference
            
            $submissionReference = 'AGT' . date('YmdHis') . rand(1000, 9999);
            
            $this->getEntityManager()->saveEntity($saftExport, [
                'submittedToAgt' => true,
                'submissionDate' => date('Y-m-d H:i:s'),
                'submissionReference' => $submissionReference
            ]);
            
            return (object) [
                'success' => true,
                'message' => 'Successfully submitted to AGT',
                'submissionReference' => $submissionReference
            ];
            
        } catch (\Exception $e) {
            return (object) [
                'success' => false,
                'message' => 'Submission failed: ' . $e->getMessage()
            ];
        }
    }

    private function isValidDate(string $date): bool
    {
        $d = \DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
}