<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Campo de perfil para los airframes propios de SimBrief del piloto.
 *
 * POR QUE UN CAMPO DE TEXTO Y NO UN userid
 * ----------------------------------------
 * SimBrief no expone los airframes guardados de un usuario: `inputs.airframes.json`
 * es un fichero estatico (devuelve los mismos bytes con y sin `userid`) y no hay
 * endpoint por usuario. El unico camino soportado es que el piloto copie el
 * "Internal ID" de su editor de airframes (por ejemplo `349674_1661382482154`) y
 * nos lo de; eso es lo que se guarda aqui, con el tipo de avion al que aplica.
 *
 * El valor es privado (`private = 1`): no sale en el perfil publico, pero si en
 * el formulario de edicion, que es el que filtra por `private = 0` solo en la
 * vista publica (`UserRepository::getUserFields()`).
 */
return new class extends Migration
{
    private const FIELD_NAME = 'SimBrief Airframe IDs';

    public function up(): void
    {
        if (DB::table('user_fields')->where('name', self::FIELD_NAME)->exists()) {
            return;
        }

        DB::table('user_fields')->insert([
            'name' => self::FIELD_NAME,
            'description' => 'Opcional. Tus airframes de SimBrief, por tipo de avion: '
                .'B738=349674_1661382482154, A320=80_1709125568637. '
                .'El Internal ID esta arriba del todo en tu editor de airframes de SimBrief. '
                .'Con esto el despacho planifica con tu avion en vez de con el generico.',
            'show_on_registration' => 0,
            'required'             => 0,
            'private'              => 1,
            'internal'             => 0,
            'active'               => 1,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('user_fields')->where('name', self::FIELD_NAME)->value('id');

        if ($id !== null) {
            DB::table('user_field_values')->where('user_field_id', $id)->delete();
            DB::table('user_fields')->where('id', $id)->delete();
        }
    }
};
