<?php
    // Exemplo de variável dinâmica
    $moduleName = "Module 1";

    // Detecta horário do dia para exemplo simples
    $hora = date("H");
    $saudacao = $hora < 12 ? "Bom dia!" : ($hora < 18 ? "Boa tarde!" : "Boa noite!");
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $moduleName ?></title>
</head>

<body class="light-theme" aria-label="O site está utilizando o tema light" role="body">

    <h1><?= $moduleName ?></h1>

    <p><?= $saudacao ?> Seja bem-vindo ao módulo 1!</p>

    <p>
        Este conteúdo está sendo processado via <strong>PHP-FPM</strong> com Nginx.
    </p>

    <p>
        URL amigável funcionando sem <code>.php</code>.
    </p>

</body>

</html>