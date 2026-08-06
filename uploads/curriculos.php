<?php
$tipo = $_FILES['curriculo']['type'];

if($tipo != "application/pdf"){
echo "Apenas PDF permitido.";
exit;
}
?>