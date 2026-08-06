<?php

function verificarJovem(){

session_start();

if(!isset($_SESSION['jovem_id'])){
    header("Location: ../jovem/login.php");
    exit;
}

}

function verificarEmpresa(){

session_start();

if(!isset($_SESSION['empresa_id'])){
    header("Location: ../empresa/login.php");
    exit;
}

}