<?php
/**
 * RÍGEL INGENIERÍA P&S - MIGRATOR V37.3 (PROTOCOLO DE LIMPIEZA REFORZADO)
 * PROTOCOLO DE 7 PUNTOS:
 * 1. Detección automática (Antiguo / +90 días).
 * 2. Fechas editables.
 * 3. Candado 31/12/2024.
 * 4. ZIP dinámico.
 * 5. LIMPIEZA AGRESIVA: unlink($f) forzado tras ZipArchive::close().
 * 6. BOTÓN ZIP Y PURGA MANUAL PRESENTES.
 * 7. Límite de 500 correos.
 */

// --- CONFIGURACIÓN ---
$host = '{localhost:993/imap/ssl/novalidate-cert}INBOX'; 
$user = 'jarango@rigelingenieriaps.com';
$pass = 'Mrlmrlmr1*##';
$dir  = 'backups_emails_rigel/'; 
$limite_max = 500; 
$fecha_limite_rigel = '2024-12-31';

if (!file_exists($dir)) { mkdir($dir, 0755, true); }

$mbox = imap_open($host, $user, $pass) or die("Error: " . imap_last_error());

// --- DETECCIÓN DE ESTATUS ---
$total_msg = imap_num_msg($mbox);
$fecha_real_antigua = '2020-01-01';
if ($total_msg > 0) {
    $header_antiguo = imap_headerinfo($mbox, 1);
    $fecha_real_antigua = date("Y-m-d", $header_antiguo->udate);
}

$date_temp = new DateTime($fecha_real_antigua);
$date_temp->modify('+90 days');
$fecha_hasta_calculada = $date_temp->format('Y-m-d');
if (strtotime($fecha_hasta_calculada) > strtotime($fecha_limite_rigel)) { $fecha_hasta_calculada = $fecha_limite_rigel; }

echo "<html><head><title>Rígel Ingeniería P&S - V37.3</title>";
echo "<style>
    body { font-family: 'Segoe UI', sans-serif; background: #f4f7f6; padding: 20px; }
    .card { background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); max-width: 900px; margin: auto; }
    .btn { padding: 12px 24px; border-radius: 5px; cursor: pointer; font-weight: bold; border: none; text-decoration: none; display: inline-block; margin: 10px 5px; }
    .btn-main { background: #1a237e; color: white; }
    .btn-zip { background: #2e7d32; color: white; }
    .btn-danger { background: #d32f2f; color: white; }
    .log { height: 180px; overflow-y: auto; background: #1e1e1e; color: #a6e22e; padding: 15px; font-family: monospace; font-size: 11px; margin-top: 15px; border-radius: 6px; }
    .alert-box { background: #e3f2fd; border: 2px solid #2196f3; padding: 25px; border-radius: 8px; text-align: center; margin-top: 25px; }
    .info-bar { background: #e8eaf6; padding: 15px; border-radius: 5px; margin-bottom: 20px; font-size: 0.95em; color: #1a237e; border-left: 5px solid #1a237e; }
</style></head><body>";

echo "<div class='card'>
      <h2 style='text-align:center; color:#1a237e;'>RÍGEL INGENIERÍA P&S</h2>
      <p style='text-align:center; color:#666;'>Migrador V37.3 - Protocolo de Seguridad</p><hr>";

echo "<div class='info-bar'>
        📊 <b>Estatus:</b> Correo más antiguo detectado: <b>$fecha_real_antigua</b>.<br>
        📅 <b>Automático:</b> Bloque de 90 días sugerido (Modificable).
      </div>";

$val_desde = isset($_POST['f_desde']) ? $_POST['f_desde'] : $fecha_real_antigua;
$val_hasta = isset($_POST['f_hasta']) ? $_POST['f_hasta'] : $fecha_hasta_calculada;

echo "<div style='text-align:center; background:#eee; padding:20px; border-radius:8px;'>
        <form method='POST'>
            Desde: <input type='date' name='f_desde' value='$val_desde'> 
            Hasta: <input type='date' name='f_hasta' value='$val_hasta' max='$fecha_limite_rigel'>
            <button type='submit' name='accion' value='ejecutar' class='btn btn-main'>INICIAR RESPALDO</button>
        </form>
      </div>";

if (isset($_POST['accion']) && $_POST['accion'] == 'ejecutar') {
    $f_hasta_segura = (strtotime($_POST['f_hasta']) > strtotime($fecha_limite_rigel)) ? $fecha_limite_rigel : $_POST['f_hasta'];
    $emails = imap_search($mbox, 'SINCE "'.date("d-M-Y", strtotime($_POST['f_desde'])).'" BEFORE "'.date("d-M-Y", strtotime($f_hasta_segura . " +1 day")).'"');

    if ($emails) {
        $emails = array_slice($emails, 0, $limite_max); 
        $archivos_locales = [];
        $fechas_extremo = [];

        echo "<div class='log'>🚀 Descargando .eml al servidor local...<br>";
        foreach ($emails as $num) {
            $h_info = imap_headerinfo($mbox, $num);
            $remitente = $h_info->from[0]->mailbox . "@" . $h_info->from[0]->host;
            $fecha_f = date("Y-m-d", $h_info->udate);
            $fechas_extremo[] = date("dmY", $h_info->udate);
            
            $asunto_raw = isset($h_info->subject) ? $h_info->subject : 'Sin_Asunto';
            $dec = imap_mime_header_decode($asunto_raw);
            $asunto_limpio = "";
            foreach ($dec as $p) { $asunto_limpio .= mb_convert_encoding($p->text, 'UTF-8', ($p->charset == 'default' ? 'ISO-8859-1' : $p->charset)); }
            $asunto_limpio = preg_replace('/[^a-zA-Z0-9_\- ]/', '', $asunto_limpio);
            $asunto_limpio = trim(substr($asunto_limpio, 0, 60));

            $nombre_f = "ID $num _ $fecha_f _ $remitente _ $asunto_limpio.eml";
            $raw_email = imap_fetchheader($mbox, $num) . "\n" . imap_body($mbox, $num);
            
            if (file_put_contents($dir . $nombre_f, $raw_email)) {
                $archivos_locales[] = $dir . $nombre_f;
                echo "Descargado: $nombre_f<br>";
            }
        }
        echo "</div>";

        if (count($archivos_locales) > 0) {
            $nom_zip = "Backup_" . reset($fechas_extremo) . "_" . end($fechas_extremo) . ".zip";
            $ruta_zip = $dir . $nom_zip;
            $zip = new ZipArchive();
            
            if ($zip->open($ruta_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
                foreach ($archivos_locales as $f) { $zip->addFile($f, basename($f)); }
                $zip->close();
                
                // --- PUNTO 5: LIMPIEZA AGRESIVA (REFORZADO) ---
                echo "<div class='log' style='color:#00e5ff;'>🧹 PROTOCOLO DE LIMPIEZA LOCAL...<br>";
                foreach ($archivos_locales as $f) {
                    if (file_exists($f)) {
                        unlink($f); 
                        echo "Eliminado localmente: " . basename($f) . "<br>";
                    }
                }
                echo "Carpeta local limpia.</div>";

                // --- BOTONES DE ACCIÓN ---
                echo "<div class='alert-box'>
                        <h3>📦 PASO 1: DESCARGAR ZIP</h3>
                        <p>Archivo: <b>$nom_zip</b></p>
                        <a href='$ruta_zip' download class='btn btn-zip'>DESCARGAR AHORA</a>
                      </div>";

                echo "<div style='background:#ffebee; border:2px solid #d32f2f; padding:25px; border-radius:8px; text-align:center; margin-top:20px;'>
                        <h3>⚠️ PASO 2: PURGAR SERVIDOR (ROUNDCUBE)</h3>
                        <form method='POST'>
                            <input type='hidden' name='ids' value='".implode(',', $emails)."'>
                            <button type='submit' name='accion' value='purgar' class='btn btn-danger'>BORRAR CORREOS DEFINITIVAMENTE</button>
                        </form>
                      </div>";
            }
        }
    } else { echo "<p style='text-align:center; padding:20px;'>❌ No se hallaron correos.</p>"; }
}

if (isset($_POST['accion']) && $_POST['accion'] == 'purgar') {
    foreach (explode(',', $_POST['ids']) as $id) { imap_delete($mbox, $id); }
    imap_expunge($mbox);
    echo "<div class='alert-box' style='background:#e8f5e9; color:green; border-color:green;'>🏁 PURGA COMPLETADA. <a href='?' class='btn btn-main'>CONTINUAR</a></div>";
}
imap_close($mbox);
echo "</div><p style='text-align:center; color:#999; font-size:11px; margin-top:20px;'>&copy; 2026 Rígel Ingeniería P&S</p></body></html>";