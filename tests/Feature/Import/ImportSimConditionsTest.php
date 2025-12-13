<?php

namespace Tests\Feature\Import;

use App\Domain\Import\Imports\SalesConditionImport;
use App\Domain\Import\Models\Import;
use App\Domain\Import\Models\SalesCondition;
use App\Domain\Import\Models\Simcard;
use App\Domain\Store\Models\Store;
use App\Domain\User\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class ImportSimConditionsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup previo
        $this->user = User::factory()->create();
        $this->store = Store::factory()->create(['idpos' => '12345']);
    }

    /** @test */
    public function it_imports_sim_conditions_successfully()
    {
        // Arrange
        // Mocking excel file with "Tabla" sheet
        // Como es dificil mockear mult-sheet en memoria con Maatwebsite fakes a nivel tan bajo sin crear archivo real,
        // confiaremos en la integracion o crearemos un archivo temporal real si es necesario.
        // Pero Maatwebsite tiene fake();

        // Simular archivo
        // No podemos usar Excel::fake() facilmente para validar logica interna de `onRow` real.
        // Vamos a instanciar el Import directamente y alimentarlo con arrays simulando filas.
        // Es mas test unitario de la clase Import que feature full end-to-end con archivo, pero valida la logica.

        // Sin embargo, el user pidio una Feature test.
        // Crearemos un Excel real en runtime.

        $this->markTestSkipped('Requires PhpSpreadsheet writing logic or simplified unit testing');
        // Paremos un momento: Escribir un excel real en tests es lento y complejo de configurar en este entorno sin helper.
        // Haremos tests probando la logica de Import::onRow directamente o simulando la lectura.
    }

    /** @test */
    public function on_row_creates_sim_and_condition()
    {
        // 1. Arrange
        $import = Import::create(['type' => 'sales_condition', 'status' => 'processing']);
        $importer = new SalesConditionImport($import->id);

        $row = [
            'ICCID' => '895710100001',
            'NUMERODETELEFONO' => '3001234567',
            'IDPOS' => '12345',
            'VALOR' => '10000',
            'RESIDUAL' => '7%', // 0.07
            'POBLACION' => 'BOGOTA',
            'FECHA VENTA' => '2025-04-01',
        ];

        // 2. Act
        // Simulamos onRow. Necesitamos un objeto Row mockeado o simplemente llamar logica interna si fuera publica.
        // Pero onRow espera un objeto Row de maatwebsite.
        // Podemos usar Reflection o mejor aun, si extrajimos la logica... 
        // Mejor opcion: Crear un archivo excel temporal simplificado.

        // Vamos a usar una estrategia mas robusta:
        // Crear un Spreadsheet, guardar en tmp, pasar al import.

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tabla');
        $sheet->fromArray([
            ['ICCID', 'NUMERODETELEFONO', 'IDPOS', 'VALOR', 'RESIDUAL', 'POBLACION', 'FECHA VENTA'],
            ['895710100001', '3001234567', '12345', '10000', '7%', 'BOGOTA', '01/04/2025'],
            ['895710100002', '3009876543', '12345', '20000', '0.07', 'MEDELLIN', '02/04/2025'],
            ['895710100001', '3001234567', '12345', '10000', '7%', 'BOGOTA', '01/04/2025'], // Duplicado en archivo
        ]);

        // Hoja data (debe ignorarse)
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('data');
        $sheet2->setCellValue('A1', 'Should ignore');

        $path = sys_get_temp_dir() . '/test_import.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        $importModel = Import::create(['type' => 'sales_condition', 'status' => 'processing', 'created_by' => $this->user->id]);

        // Act
        $importer = new SalesConditionImport($importModel->id);
        $importer->import($path);

        // Assert
        // 1. Simcard Creadas
        $this->assertDatabaseHas('simcards', ['iccid' => '895710100001', 'phone_number' => '3001234567']);
        $this->assertDatabaseHas('simcards', ['iccid' => '895710100002', 'phone_number' => '3009876543']);

        // 2. Conditions Creadas
        // Sim 1
        $sim1 = Simcard::where('iccid', '895710100001')->first();
        $this->assertDatabaseHas('sales_conditions', [
            'simcard_id' => $sim1->id,
            'idpos' => '12345',
            'sale_price' => 10000,
            'commission_percentage' => 7, // 0.07 * 100 = 7
            'period_year' => 2025,
            'period_month' => 4,
        ]);

        // Sim 2
        $sim2 = Simcard::where('iccid', '895710100002')->first();
        $this->assertDatabaseHas('sales_conditions', [
            'simcard_id' => $sim2->id,
            'commission_percentage' => 7,
        ]);

        // 3. Stats
        $stats = $importer->getStats();
        $this->assertEquals(2, $stats['inserted']); // 2 unicos
        $this->assertEquals(1, $stats['duplicates']); // 1 duplicado en archivo
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEquals(3, $stats['total_processed']);

        @unlink($path);
    }

    /** @test */
    public function it_updates_existing_condition()
    {
        // Arrange
        $sim = Simcard::create(['iccid' => '895710100001', 'phone_number' => '3001234567']);
        SalesCondition::create([
            'simcard_id' => $sim->id,
            'period_year' => 2025,
            'period_month' => 4,
            'sale_price' => 5000,
            'commission_percentage' => 5,
            'idpos' => '12345',
        ]);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tabla');
        $sheet->fromArray([
            ['ICCID', 'NUMERODETELEFONO', 'IDPOS', 'VALOR', 'RESIDUAL', 'POBLACION', 'FECHA VENTA'],
            ['895710100001', '3001234567', '12345', '9000', '10%', 'CALI', '01/04/2025'],
        ]);

        $path = sys_get_temp_dir() . '/test_update.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        $importModel = Import::create(['type' => 'sales_condition', 'status' => 'processing']);

        // Act
        $importer = new SalesConditionImport($importModel->id);
        $importer->import($path);

        // Assert
        $this->assertDatabaseHas('sales_conditions', [
            'simcard_id' => $sim->id,
            'sale_price' => 9000,
            'commission_percentage' => 10,
            'population' => 'CALI'
        ]);

        $stats = $importer->getStats();
        $this->assertEquals(1, $stats['updated']);
        $this->assertEquals(0, $stats['inserted']);

        @unlink($path);
    }

    /** @test */
    public function it_fails_with_invalid_idpos()
    {
        // Arrange
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tabla');
        $sheet->fromArray([
            ['ICCID', 'NUMERODETELEFONO', 'IDPOS', 'VALOR', 'RESIDUAL', 'POBLACION', 'FECHA VENTA'],
            ['895710100001', '3001234567', '99999', '10000', '7%', 'BOGOTA', '01/04/2025'], // IDPOS 99999 no existe
        ]);

        $path = sys_get_temp_dir() . '/test_invalid.xlsx';
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($path);

        $importModel = Import::create(['type' => 'sales_condition', 'status' => 'processing']);

        // Act
        $importer = new SalesConditionImport($importModel->id);
        $importer->import($path);

        // Assert
        $stats = $importer->getStats();
        $this->assertEquals(0, $stats['inserted']);
        $this->assertEquals(1, $stats['skipped']);

        $errors = $importer->getErrors();
        $this->assertNotEmpty($errors['skipped_rows']);
        $this->assertStringContainsString('no existe', $errors['skipped_rows'][0]['message'] ?? ''); // Check 'message' key

        @unlink($path);
    }
}
