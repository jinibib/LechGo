 
<?php

class Product {
private $conn;
public function __construct($conn){ $this->conn = $conn; }
public function getAllProducts(){ return $this->conn->query("SELECT * FROM products")->fetch_all(MYSQLI_ASSOC); }
public function getProductById($id){
$stmt = $this->conn->prepare("SELECT * FROM products WHERE id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
return $stmt->get_result()->fetch_assoc();
}
}