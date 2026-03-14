<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

class ImportAirports extends Command
{
    protected $signature = 'import:airports {file=airports.csv}';
    protected $description = 'Importar aeropuertos desde CSV';

    public function handle()
    {
        $file = storage_path('app/'.$this->argument('file'));

        if (!file_exists($file)) {
            $this->error("Archivo no encontrado: $file");
            return 1;
        }

        $csv = Reader::createFromPath($file, 'r');
        $csv->setHeaderOffset(0); // Usar la primera fila como cabeceras

        $records = $csv->getRecords();

        $this->info('Importando aeropuertos...');

        $count = 0;
        foreach ($records as $record) {
            // columnas conocidas del dataset
            $icao = trim($record['icao']);
            $iata = trim($record['iata']);
            $name = trim($record['name']);
            $city = trim($record['city']);
            $country = trim($record['country']);
            $lat = $record['lat'] ?: null;
            $lon = $record['lon'] ?: null;

            // evitar registros vacíos
            if (empty($icao) && empty($iata)) {
                continue;
            }

            // Insertar o actualizar
            DB::table('airports')->updateOrInsert([
                'icao' => $icao,
                'iata' => $iata,
            ], [
                'name' => $name,
                'city' => $city,
                'country' => $country,
                'latitude' => $lat,
                'longitude' => $lon,
            ]);

            $count++;
        }

        $this->info("Importados / actualizados: $count aeropuertos.");
        return 0;
    }
}