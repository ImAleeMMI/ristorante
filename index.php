<?php

define('HOST', 'mysql:dbname=ristorante;host=localhost');
define('USER', 'root');
define('PASSWORD', 'root');

function initRestaurant()
{
    try {
        $pdo = new PDO(HOST, USER, PASSWORD);

        $create_restaurant = "
                CREATE TABLE IF NOT EXISTS `ristorante` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nome` VARCHAR(255) NOT NULL,
                    `indirizzo` VARCHAR(255) NOT NULL,
                    `telefono` VARCHAR(255) NOT NULL,
                    `orari_di_apertura` TEXT NOT NULL,
                     PRIMARY KEY (`id`)
                ) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;";
        $pdo->exec($create_restaurant);
        if (!count($pdo->query('SELECT * FROM ristorante')->fetchAll())) {
            $populate_restaurant =  "
                INSERT INTO ristorante (nome, indirizzo, telefono, orari_di_apertura)
                VALUES ('Ristorante da Mario','Via Roma, 123','+39 123 456789','{\"lunedì\" : \"09:00 - 22:00\",
                                                                            \"martedì\" : \"09:00 - 22:00\",
                                                                            \"mercoledì\" : \"09:00 - 22:00\",
                                                                            \"giovedì\" : \"09:00 - 22:00\",
                                                                            \"venerdì\" : \"09:00 - 23:00\",
                                                                            \"sabato\" : \"10:00 - 23:00\",
                                                                            \"domenica\" : \"10:00 - 21:00\"
                }')
              ";
            $pdo->exec($populate_restaurant);
        }

        $create_review = "
                CREATE TABLE IF NOT EXISTS `recensioni` (
                    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `nome_cliente` VARCHAR(255) NOT NULL,
                    `voto` INT(10) NOT NULL,
                    `commento` VARCHAR(255) NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;";
        $pdo->exec($create_review);
        if (!count($pdo->query('SELECT * FROM recensioni')->fetchAll())) {
            $populate_review = "
                    INSERT INTO recensioni (nome_cliente, voto, commento)
                    VALUES ";
            $votes_source = file_get_contents("recensioni.json");
            $votes_decode = json_decode($votes_source);
            foreach ($votes_decode->recensioni as $key => $vote) {
                $nome_cliente = '"' . $vote->nome_cliente . '"';
                $commento = '"' . $vote->commento . '"';
                $populate_review .= "($nome_cliente, $vote->voto, $commento)";
                if ($key !== array_key_last($votes_decode->recensioni)) {
                    $populate_review .= ', ';
                }
            }
            $populate_restaurant .= ";";
            $pdo->exec($populate_review);
        }

        $create_reservation_table = "CREATE TABLE IF NOT EXISTS `prenotazioni` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `nome_cognome` VARCHAR(255) NOT NULL,
            `data` VARCHAR(255) NOT NULL,
            `orario` VARCHAR(255) NOT NULL,
            `persone` INT(10) NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE = InnoDB CHARSET=utf8mb4 COLLATE utf8mb4_general_ci;";
        $pdo->exec($create_reservation_table);
        if (!count($pdo->query('SELECT * FROM prenotazioni')->fetchAll())) {
            $prenotation = 'prenotazioni.csv';
            $handler = fopen($prenotation, 'r');
            $index = 0;
            $sql_populate_reservation = 'INSERT INTO prenotazioni (nome_cognome, data, orario, persone) VALUES ';
            while (!feof($handler)) {
                $row = fgetcsv($handler);
                if ($index !== 0) {
                    $s = '(';
                    $s .= '"' . $row[0] . '",';
                    $s .= '"' . $row[1] . '",';
                    $s .= '"' . $row[2] . '",';
                    $s .= $row[3];
                    $s .= ')';

                    $sql_populate_reservation .= $s;
                    if (!feof($handler) === true) {
                        $sql_populate_reservation .= ',';
                    }
                }
                $index++;
            }
            $sql_populate_reservation .= ';';
            fclose($handler);
            $pdo->exec($sql_populate_reservation);
        }
    } catch (PDOException $e) {
        die("ERRORE: Impossibile stabilire una connessione al database");
    }
}

function addReservation()
{
    $pdo = new PDO(HOST, USER, PASSWORD);
    $firstlast_name = $_POST['nome_cognome'];
    $date_prenotation = $_POST['data_prenotazione'];
    $hour = $_POST['orario'];
    $n_person = $_POST['numero_persone'];
    $sql_add_reservation = "INSERT INTO prenotazioni (nome_cognome, data, orario, persone) VALUES ('$firstlast_name', '$date_prenotation', '$hour', '$n_person')";
    $pdo->exec($sql_add_reservation);
}

function addReview()
{
    $pdo = new PDO(HOST, USER, PASSWORD);
    $name_client = $_POST['nome_cliente'];
    $voto = $_POST['voto'];
    $comment = $_POST['commento'];
    $sql_add_review = "INSERT INTO recensioni (nome_cliente, voto, commento) VALUES ('$name_client', '$voto', '$comment')";
    $pdo->exec($sql_add_review);
}

function export($entity = 'all')
{

    $pdo = new PDO(HOST, USER, PASSWORD);
    function exportReview($pdoParam)
    {

        $object = $pdoParam->query("SELECT * FROM recensioni");
        $array = $object->fetchAll();
        $filename = 'export/recensioni.csv';
        $handler = fopen($filename, 'w+');
        fputcsv($handler, array(
            'id',
            'nome_cliente',
            'voto',
            'commento'
        ));

        for ($i = 0; $i < $object->rowCount(); $i++) {
            $finalrow = array();
            for ($j = 0; $j < $object->columnCount(); $j++) {
                $finalrow[] = $array[$i][$j];
            }
            fputcsv($handler, $finalrow);
        }
        fclose($handler);
    }
    function exportReservation($pdoParam)
    {
        $object = $pdoParam->query("SELECT * FROM prenotazioni");
        $array = $object->fetchAll(PDO::FETCH_NUM);
        $json_prenotazioni = json_encode($array);
        $filename = 'export/prenotazioni.json';
        file_put_contents($filename, $json_prenotazioni);
    }
    switch ($_POST['export']) {
        case 'recensioni': {
                exportReview($pdo);
                break;
            }
        case 'prenotazioni': {
                exportReservation($pdo);
                break;
            }

        case 'all': {
                exportReview($pdo);
                exportReservation($pdo);
                break;
            }
    }
}

function download()
{
    $pdo = new PDO(HOST, USER, PASSWORD);
    $object = $pdo->query("SELECT * FROM recensioni");
    $array = $object->fetchAll();
    $handler = fopen('php://output', 'w');
    fputcsv($handler, array(
        'id',
        'nome_cliente',
        'voto',
        'commento'
    ));

    for ($i = 0; $i < $object->rowCount(); $i++) {
        $finalrow = array();
        for ($j = 0; $j < $object->columnCount(); $j++) {
            $finalrow[] = $array[$i][$j];
        }
        fputcsv($handler, $finalrow);
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="export/recensioni.csv";');
    fpassthru($handler);
    die();
}

if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'init': {
                initRestaurant();
                break;
            }
        case 'addReservation': {
                addReservation();
                break;
            }
        case 'addReview': {
                addReview();
                break;
            }
        case 'export': {
                export($_POST['export']);
                break;
            }
        case 'download': {
                download();
                break;
            }
    }
}

?>
<!DOCTYPE html>
<html>

<head>
    <title>Ristorante</title>
    <link href="styleristorante.css" rel="stylesheet" type="text/css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
</head>

<body>
    <form action="index.php" method="POST">
        <input type="hidden" name="action" value="init">
        <button type="submit" class="btn">Inizializza</button>
    </form>
    <div class="container-sm">
        <form action="index.php" method="POST">
            <h1 class="display-3">Ristorante da Mario</h1>
            <h2 class="display-4">Prenotazioni</h2>
            <input type="hidden" name="action" value="addReservation">
            <div class="mb-3">
                <label for="nome_cognome" class="form-label">Nome e Cognome</label>
                <input type="text" class="form-control" id="nome_cognome" name="nome_cognome">
            </div>
            <div class="mb-3">
                <label for="data_prenotazione" class="form-label">Data Prenotazione: </label>
                <input type="text" class="form-control" id="data_prenotazione" name="data_prenotazione">
            </div>
            <div class="mb-3">
                <label for="orario" class="form-label">Orario: </label>
                <input type="text" class="form-control" id="orario" name="orario">
            </div>
            <div class="mb-3">
                <label for="numero_persone" class="form-label">Numero persone: </label>
                <input type="text" class="form-control" id="numero_persone" name="numero_persone">
            </div>
            <input type="submit" class="btn btn-danger" value="Conferma Prenotazioni">
        </form>
        <br>
        <form action="index.php" method="POST">
            <h2 class="display-4">Recensioni</h2>
            <input type="hidden" name="action" value="addReview">
            <div class="mb-3">
                <label for="nome_cliente" class="form-label">Nome Cliente: </label>
                <input type="text" class="form-control" id="nome_cliente" name="nome_cliente">
            </div>
            <div class="mb-3">
                <label for="voto">Voto: </label>
                <input type="text" class="form-control" id="voto" name="voto">
            </div>
            <div class="mb-3">
                <label for="commento" class="form-label">Commento:</label>
                <textarea id="commento" class="form-control" name="commento" rows="3"></textarea>
            </div>
            <input type="submit" class="btn btn-success" value="Conferma Recensioni">
        </form>
        <br>
        <form action="index.php" method="POST">
            <input type="hidden" name="action" value="export">
            <div class="mb-3">
                <label for="export" class="form-label">Seleziona cosa vuoi esportare: </label>
                <select class="form-select" name="export" id="export">
                    <option value="prenotazioni">Prenotazione</option>
                    <option value="recensioni">Recensione</option>
                    <option value="all">Esporta tutto</option>
                </select>
            </div>
            <input type="submit" class="btn btn-success" value="Esporta">
        </form>
        <form action="index.php" method="POST">
            <input type="hidden" name="action" value="download">
            <input type="submit" value="Download Csv">

        </form>
    </div>
</body>

</html>