<?php

namespace Espo\Modules\SaftAngola\Services;

use Espo\Core\Services\Base;
use Espo\Core\Di;
use Espo\ORM\EntityManager;
use Espo\Core\Utils\DateTime as DateTimeUtil;

class SaftGenerator extends Base implements
    Di\EntityManagerAware,
    Di\ConfigAware,
    Di\FileManagerAware
{
    use Di\EntityManagerSetter;
    use Di\ConfigSetter;
    use Di\FileManagerSetter;

    private $entityManager;
    private $config;
    private $fileManager;

    public function generateSaftXml(string $saftConfigId, string $periodStart, string $periodEnd): array
    {
        $saftConfig = $this->entityManager->getEntity('SaftConfig', $saftConfigId);
        
        if (!$saftConfig) {
            throw new \Exception('SAFT Configuration not found');
        }

        $result = [
            'success' => false,
            'xmlContent' => '',
            'fileSize' => 0,
            'totalInvoices' => 0,
            'totalAccounts' => 0,
            'totalProducts' => 0,
            'validationErrors' => '',
            'filePath' => ''
        ];

        try {
            // Create XML Document
            $xml = new \DOMDocument('1.0', 'UTF-8');
            $xml->formatOutput = true;

            // Create root element with namespaces
            $auditFile = $xml->createElement('AuditFile');
            $auditFile->setAttribute('xmlns', 'urn:OECD:StandardAuditFile-Tax:PT_1.01_01');
            $auditFile->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
            $auditFile->setAttribute('xsi:schemaLocation', 'urn:OECD:StandardAuditFile-Tax:PT_1.01_01 SAFTAO1.01_01.xsd');
            $xml->appendChild($auditFile);

            // Header
            $header = $this->createHeader($xml, $saftConfig, $periodStart, $periodEnd);
            $auditFile->appendChild($header);

            // Master Files
            $masterFiles = $this->createMasterFiles($xml, $saftConfig, $periodStart, $periodEnd);
            $auditFile->appendChild($masterFiles);

            // General Ledger Entries
            $generalLedger = $this->createGeneralLedger($xml, $periodStart, $periodEnd);
            $auditFile->appendChild($generalLedger);

            // Source Documents
            $sourceDocuments = $this->createSourceDocuments($xml, $periodStart, $periodEnd);
            $auditFile->appendChild($sourceDocuments);

            $xmlContent = $xml->saveXML();
            
            // Save to file
            $fileName = sprintf('SAFT_AO_%s_%s_%s.xml', 
                $saftConfig->get('taxRegistrationNumber'),
                date('Y', strtotime($periodStart)),
                date('YmdHis')
            );
            
            $filePath = 'data/saft_exports/' . $fileName;
            $this->fileManager->putContents($filePath, $xmlContent);

            $result = [
                'success' => true,
                'xmlContent' => base64_encode(gzcompress($xmlContent)),
                'fileSize' => strlen($xmlContent),
                'totalInvoices' => $this->countInvoices($periodStart, $periodEnd),
                'totalAccounts' => $this->countAccounts(),
                'totalProducts' => $this->countProducts(),
                'validationErrors' => '',
                'filePath' => $filePath
            ];

        } catch (\Exception $e) {
            $result['validationErrors'] = $e->getMessage();
        }

        return $result;
    }

    private function createHeader(\DOMDocument $xml, $saftConfig, string $periodStart, string $periodEnd): \DOMElement
    {
        $header = $xml->createElement('Header');

        // Audit File Version
        $auditFileVersion = $xml->createElement('AuditFileVersion', '1.01_01');
        $header->appendChild($auditFileVersion);

        // Company ID
        $companyID = $xml->createElement('CompanyID', htmlspecialchars($saftConfig->get('taxRegistrationNumber')));
        $header->appendChild($companyID);

        // Tax Registration Number
        $taxRegistrationNumber = $xml->createElement('TaxRegistrationNumber', htmlspecialchars($saftConfig->get('taxRegistrationNumber')));
        $header->appendChild($taxRegistrationNumber);

        // Tax Accounting Basis
        $taxAccountingBasis = $xml->createElement('TaxAccountingBasis', 'F'); // F = Faturação, C = Contabilidade
        $header->appendChild($taxAccountingBasis);

        // Company Name
        $companyName = $xml->createElement('CompanyName', htmlspecialchars($saftConfig->get('companyName')));
        $header->appendChild($companyName);

        // Business Name (same as company name for most cases)
        $businessName = $xml->createElement('BusinessName', htmlspecialchars($saftConfig->get('companyName')));
        $header->appendChild($businessName);

        // Company Address
        $companyAddress = $this->createCompanyAddress($xml, $saftConfig);
        $header->appendChild($companyAddress);

        // Fiscal Year
        $fiscalYear = $xml->createElement('FiscalYear', $saftConfig->get('fiscalYear'));
        $header->appendChild($fiscalYear);

        // Start Date
        $startDate = $xml->createElement('StartDate', $periodStart);
        $header->appendChild($startDate);

        // End Date
        $endDate = $xml->createElement('EndDate', $periodEnd);
        $header->appendChild($endDate);

        // Currency Code
        $currencyCode = $xml->createElement('CurrencyCode', $saftConfig->get('currencyCode'));
        $header->appendChild($currencyCode);

        // Date Created
        $dateCreated = $xml->createElement('DateCreated', date('Y-m-d'));
        $header->appendChild($dateCreated);

        // Tax Entity
        $taxEntity = $xml->createElement('TaxEntity', '0'); // 0 = Sede
        $header->appendChild($taxEntity);

        // Product Company Tax ID
        $productCompanyTaxID = $xml->createElement('ProductCompanyTaxID', htmlspecialchars($saftConfig->get('taxRegistrationNumber')));
        $header->appendChild($productCompanyTaxID);

        // Software Validation Number (if available)
        $softwareValidationNumber = $xml->createElement('SoftwareValidationNumber', '0');
        $header->appendChild($softwareValidationNumber);

        // Product ID
        $productID = $xml->createElement('ProductID', htmlspecialchars($saftConfig->get('softwareName')));
        $header->appendChild($productID);

        // Product Version
        $productVersion = $xml->createElement('ProductVersion', htmlspecialchars($saftConfig->get('softwareVersion')));
        $header->appendChild($productVersion);

        // Header Comment
        $headerComment = $xml->createElement('HeaderComment', 'Ficheiro SAFT-AO gerado automaticamente pelo ' . htmlspecialchars($saftConfig->get('softwareName')));
        $header->appendChild($headerComment);

        // Telephone and Fax (optional)
        $telephone = $xml->createElement('Telephone', '');
        $header->appendChild($telephone);

        $fax = $xml->createElement('Fax', '');
        $header->appendChild($fax);

        // Email (optional)
        $email = $xml->createElement('Email', '');
        $header->appendChild($email);

        // Website (optional)
        $website = $xml->createElement('Website', '');
        $header->appendChild($website);

        return $header;
    }

    private function createCompanyAddress(\DOMDocument $xml, $saftConfig): \DOMElement
    {
        $companyAddress = $xml->createElement('CompanyAddress');

        $buildingNumber = $xml->createElement('BuildingNumber', '');
        $companyAddress->appendChild($buildingNumber);

        $streetName = $xml->createElement('StreetName', '');
        $companyAddress->appendChild($streetName);

        $addressDetail = $xml->createElement('AddressDetail', htmlspecialchars($saftConfig->get('addressDetail')));
        $companyAddress->appendChild($addressDetail);

        $city = $xml->createElement('City', htmlspecialchars($saftConfig->get('city')));
        $companyAddress->appendChild($city);

        $postalCode = $xml->createElement('PostalCode', htmlspecialchars($saftConfig->get('zipCode') ?: ''));
        $companyAddress->appendChild($postalCode);

        $region = $xml->createElement('Region', htmlspecialchars($saftConfig->get('region')));
        $companyAddress->appendChild($region);

        $country = $xml->createElement('Country', $saftConfig->get('countryCode'));
        $companyAddress->appendChild($country);

        return $companyAddress;
    }

    private function createMasterFiles(\DOMDocument $xml, $saftConfig, string $periodStart, string $periodEnd): \DOMElement
    {
        $masterFiles = $xml->createElement('MasterFiles');

        // General Ledger Accounts
        $generalLedgerAccounts = $this->createGeneralLedgerAccounts($xml);
        $masterFiles->appendChild($generalLedgerAccounts);

        // Customers
        $customers = $this->createCustomers($xml, $periodStart, $periodEnd);
        $masterFiles->appendChild($customers);

        // Suppliers
        $suppliers = $this->createSuppliers($xml, $periodStart, $periodEnd);
        $masterFiles->appendChild($suppliers);

        // Products
        $products = $this->createProducts($xml, $periodStart, $periodEnd);
        $masterFiles->appendChild($products);

        // Tax Table
        $taxTable = $this->createTaxTable($xml);
        $masterFiles->appendChild($taxTable);

        return $masterFiles;
    }

    private function createGeneralLedgerAccounts(\DOMDocument $xml): \DOMElement
    {
        $generalLedgerAccounts = $xml->createElement('GeneralLedgerAccounts');

        // Basic chart of accounts - this would typically come from your accounting module
        $accounts = [
            ['111', 'Caixa', 'Cash'],
            ['112', 'Depósitos à Ordem', 'Bank'],
            ['121', 'Clientes', 'Receivables'],
            ['221', 'Fornecedores', 'Payables'],
            ['261', 'Capital', 'Equity'],
            ['311', 'Compras', 'Purchases'],
            ['711', 'Vendas', 'Sales']
        ];

        foreach ($accounts as $accountData) {
            $account = $xml->createElement('Account');
            
            $accountID = $xml->createElement('AccountID', $accountData[0]);
            $account->appendChild($accountID);
            
            $accountDescription = $xml->createElement('AccountDescription', $accountData[1]);
            $account->appendChild($accountDescription);
            
            $standardAccountID = $xml->createElement('StandardAccountID', $accountData[0]);
            $account->appendChild($standardAccountID);
            
            $groupingCategory = $xml->createElement('GroupingCategory', $accountData[2]);
            $account->appendChild($groupingCategory);
            
            $groupingCode = $xml->createElement('GroupingCode', substr($accountData[0], 0, 1));
            $account->appendChild($groupingCode);
            
            $taxonomyCode = $xml->createElement('TaxonomyCode', '');
            $account->appendChild($taxonomyCode);
            
            $generalLedgerAccounts->appendChild($account);
        }

        return $generalLedgerAccounts;
    }

    private function createCustomers(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $customers = $xml->createElement('Customers');

        // Get customers from EspoCRM accounts
        $accountRepository = $this->entityManager->getRepository('Account');
        $accounts = $accountRepository->find([
            'whereClause' => [
                'createdAt<=' => $periodEnd,
                'type' => 'Customer'
            ]
        ]);

        foreach ($accounts as $account) {
            $customer = $xml->createElement('Customer');
            
            $customerID = $xml->createElement('CustomerID', $account->getId());
            $customer->appendChild($customerID);
            
            $accountID = $xml->createElement('AccountID', '121'); // Clientes
            $customer->appendChild($accountID);
            
            $customerTaxID = $xml->createElement('CustomerTaxID', $account->get('sicCode') ?: '999999999');
            $customer->appendChild($customerTaxID);
            
            $companyName = $xml->createElement('CompanyName', htmlspecialchars($account->get('name')));
            $customer->appendChild($companyName);
            
            // Billing Address
            $billingAddress = $this->createBillingAddress($xml, $account);
            $customer->appendChild($billingAddress);
            
            $telephone = $xml->createElement('Telephone', htmlspecialchars($account->get('phoneNumber') ?: ''));
            $customer->appendChild($telephone);
            
            $fax = $xml->createElement('Fax', '');
            $customer->appendChild($fax);
            
            $email = $xml->createElement('Email', htmlspecialchars($account->get('emailAddress') ?: ''));
            $customer->appendChild($email);
            
            $website = $xml->createElement('Website', htmlspecialchars($account->get('website') ?: ''));
            $customer->appendChild($website);
            
            $selfBillingIndicator = $xml->createElement('SelfBillingIndicator', '0');
            $customer->appendChild($selfBillingIndicator);
            
            $customers->appendChild($customer);
        }

        return $customers;
    }

    private function createBillingAddress(\DOMDocument $xml, $account): \DOMElement
    {
        $billingAddress = $xml->createElement('BillingAddress');
        
        $buildingNumber = $xml->createElement('BuildingNumber', '');
        $billingAddress->appendChild($buildingNumber);
        
        $streetName = $xml->createElement('StreetName', '');
        $billingAddress->appendChild($streetName);
        
        $addressDetail = $xml->createElement('AddressDetail', htmlspecialchars($account->get('billingAddressStreet') ?: ''));
        $billingAddress->appendChild($addressDetail);
        
        $city = $xml->createElement('City', htmlspecialchars($account->get('billingAddressCity') ?: ''));
        $billingAddress->appendChild($city);
        
        $postalCode = $xml->createElement('PostalCode', htmlspecialchars($account->get('billingAddressPostalCode') ?: ''));
        $billingAddress->appendChild($postalCode);
        
        $region = $xml->createElement('Region', htmlspecialchars($account->get('billingAddressState') ?: ''));
        $billingAddress->appendChild($region);
        
        $country = $xml->createElement('Country', $account->get('billingAddressCountry') ?: 'AO');
        $billingAddress->appendChild($country);
        
        return $billingAddress;
    }

    private function createSuppliers(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $suppliers = $xml->createElement('Suppliers');
        
        // Similar to customers but for suppliers
        // This would be implemented based on your supplier data structure
        
        return $suppliers;
    }

    private function createProducts(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $products = $xml->createElement('Products');
        
        // Get products from EspoCRM
        $productRepository = $this->entityManager->getRepository('Product');
        $productList = $productRepository->find([
            'whereClause' => [
                'createdAt<=' => $periodEnd
            ]
        ]);

        foreach ($productList as $product) {
            $productElement = $xml->createElement('Product');
            
            $productType = $xml->createElement('ProductType', 'P'); // P = Product, S = Service
            $productElement->appendChild($productType);
            
            $productCode = $xml->createElement('ProductCode', htmlspecialchars($product->get('productCode') ?: $product->getId()));
            $productElement->appendChild($productCode);
            
            $productGroup = $xml->createElement('ProductGroup', htmlspecialchars($product->get('category') ?: 'Outros'));
            $productElement->appendChild($productGroup);
            
            $productDescription = $xml->createElement('ProductDescription', htmlspecialchars($product->get('name')));
            $productElement->appendChild($productDescription);
            
            $productNumberCode = $xml->createElement('ProductNumberCode', htmlspecialchars($product->get('productCode') ?: $product->getId()));
            $productElement->appendChild($productNumberCode);
            
            $products->appendChild($productElement);
        }
        
        return $products;
    }

    private function createTaxTable(\DOMDocument $xml): \DOMElement
    {
        $taxTable = $xml->createElement('TaxTable');
        
        // Angola VAT rates
        $taxTypes = [
            ['IVA', 'VAT', 'P', '14.00', 'AO'],
            ['IVA', 'VAT', 'I', '7.00', 'AO'],
            ['IVA', 'VAT', 'E', '0.00', 'AO']
        ];
        
        foreach ($taxTypes as $taxData) {
            $taxTableEntry = $xml->createElement('TaxTableEntry');
            
            $taxType = $xml->createElement('TaxType', $taxData[0]);
            $taxTableEntry->appendChild($taxType);
            
            $taxCountryRegion = $xml->createElement('TaxCountryRegion', $taxData[4]);
            $taxTableEntry->appendChild($taxCountryRegion);
            
            $taxCode = $xml->createElement('TaxCode', $taxData[2]);
            $taxTableEntry->appendChild($taxCode);
            
            $description = $xml->createElement('Description', $taxData[1]);
            $taxTableEntry->appendChild($description);
            
            $taxPercentage = $xml->createElement('TaxPercentage', $taxData[3]);
            $taxTableEntry->appendChild($taxPercentage);
            
            $taxTable->appendChild($taxTableEntry);
        }
        
        return $taxTable;
    }

    private function createGeneralLedger(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $generalLedger = $xml->createElement('GeneralLedger');
        
        $numberOfEntries = $xml->createElement('NumberOfEntries', '0');
        $generalLedger->appendChild($numberOfEntries);
        
        $totalDebit = $xml->createElement('TotalDebit', '0.00');
        $generalLedger->appendChild($totalDebit);
        
        $totalCredit = $xml->createElement('TotalCredit', '0.00');
        $generalLedger->appendChild($totalCredit);
        
        // Journal entries would be added here
        
        return $generalLedger;
    }

    private function createSourceDocuments(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $sourceDocuments = $xml->createElement('SourceDocuments');
        
        // Sales Invoices
        $salesInvoices = $this->createSalesInvoices($xml, $periodStart, $periodEnd);
        $sourceDocuments->appendChild($salesInvoices);
        
        // Movements of Goods
        $movementOfGoods = $this->createMovementOfGoods($xml, $periodStart, $periodEnd);
        $sourceDocuments->appendChild($movementOfGoods);
        
        // Working Documents
        $workingDocuments = $this->createWorkingDocuments($xml, $periodStart, $periodEnd);
        $sourceDocuments->appendChild($workingDocuments);
        
        // Payments
        $payments = $this->createPayments($xml, $periodStart, $periodEnd);
        $sourceDocuments->appendChild($payments);
        
        return $sourceDocuments;
    }

    private function createSalesInvoices(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $salesInvoices = $xml->createElement('SalesInvoices');
        
        $numberOfEntries = $xml->createElement('NumberOfEntries', '0');
        $salesInvoices->appendChild($numberOfEntries);
        
        $totalDebit = $xml->createElement('TotalDebit', '0.00');
        $salesInvoices->appendChild($totalDebit);
        
        $totalCredit = $xml->createElement('TotalCredit', '0.00');
        $salesInvoices->appendChild($totalCredit);
        
        // Invoice entries would be added here from EspoCRM opportunities/quotes
        
        return $salesInvoices;
    }

    private function createMovementOfGoods(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $movementOfGoods = $xml->createElement('MovementOfGoods');
        
        $numberOfMovementLines = $xml->createElement('NumberOfMovementLines', '0');
        $movementOfGoods->appendChild($numberOfMovementLines);
        
        $totalQuantityIssued = $xml->createElement('TotalQuantityIssued', '0.00');
        $movementOfGoods->appendChild($totalQuantityIssued);
        
        return $movementOfGoods;
    }

    private function createWorkingDocuments(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $workingDocuments = $xml->createElement('WorkingDocuments');
        
        $numberOfEntries = $xml->createElement('NumberOfEntries', '0');
        $workingDocuments->appendChild($numberOfEntries);
        
        $totalDebit = $xml->createElement('TotalDebit', '0.00');
        $workingDocuments->appendChild($totalDebit);
        
        $totalCredit = $xml->createElement('TotalCredit', '0.00');
        $workingDocuments->appendChild($totalCredit);
        
        return $workingDocuments;
    }

    private function createPayments(\DOMDocument $xml, string $periodStart, string $periodEnd): \DOMElement
    {
        $payments = $xml->createElement('Payments');
        
        $numberOfEntries = $xml->createElement('NumberOfEntries', '0');
        $payments->appendChild($numberOfEntries);
        
        $totalDebit = $xml->createElement('TotalDebit', '0.00');
        $payments->appendChild($totalDebit);
        
        $totalCredit = $xml->createElement('TotalCredit', '0.00');
        $payments->appendChild($totalCredit);
        
        return $payments;
    }

    private function countInvoices(string $periodStart, string $periodEnd): int
    {
        // Count invoices/opportunities in period
        $repository = $this->entityManager->getRepository('Opportunity');
        return $repository->count([
            'whereClause' => [
                'createdAt>=' => $periodStart,
                'createdAt<=' => $periodEnd,
                'stage' => 'Closed Won'
            ]
        ]);
    }

    private function countAccounts(): int
    {
        return $this->entityManager->getRepository('Account')->count();
    }

    private function countProducts(): int
    {
        return $this->entityManager->getRepository('Product')->count();
    }
}