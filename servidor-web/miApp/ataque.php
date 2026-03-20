<?php
$conn = new mysqli("10.67.157.16","root","P@ss12345*","infraestructura");
$id = $_GET['id'];
$q = "SELECT * FROM usuarios WHERE id=$id";
$r = $conn->query($q);
while($row=$r->fetch_assoc()){
 echo $row['username']."<br>";
}
?>
