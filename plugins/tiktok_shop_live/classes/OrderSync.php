<?php
class OrderSync {
    private PDO $pdo;
    public function __construct(PDO $pdo){$this->pdo=$pdo;}
    public function saveOrder(array $order): bool {
        $sql="INSERT INTO tiktok_orders (tiktok_order_id,customer_name,total_amount,order_status,payload_json) VALUES (:id,:name,:amount,:status,:payload) ON DUPLICATE KEY UPDATE order_status=VALUES(order_status), payload_json=VALUES(payload_json)";
        $stmt=$this->pdo->prepare($sql);
        return $stmt->execute([
            ':id'=>$order['order_id'] ?? '',
            ':name'=>$order['customer_name'] ?? '',
            ':amount'=>$order['total_amount'] ?? 0,
            ':status'=>$order['status'] ?? 'unknown',
            ':payload'=>json_encode($order,JSON_UNESCAPED_UNICODE)
        ]);
    }
}
