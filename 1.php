<?php

function addOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity)
{
    $equal_price = ($ticket_adult_price * $ticket_adult_quantity) + ($ticket_kid_price * $ticket_kid_quantity);

    do {
        
        $barcode = generateUniqueBarcode();

        $bookingResponse = sendBookingRequest($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode);
		
    } while (isset($bookingResponse['error']) && $bookingResponse['error'] === 'barcode already exists');

    // Подтверждение заказа
    if (isset($bookingResponse['message']) && $bookingResponse['message'] === 'order successfully booked') {
		
        $confirmationResponse = sendConfirmationRequest($barcode);
        
        if (isset($confirmationResponse['message']) && $confirmationResponse['message'] === 'order successfully approved') {
            // Сохранение заказа в базу данных с использованием транзакции
            try {
                saveOrderToDatabase($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $equal_price);
                echo "Order successfully saved!";
            } catch (Exception $e) {
                echo "Failed to save order: " . $e->getMessage();
            }
        } else {
            echo "Order approval failed: " . $confirmationResponse['error'];
        }
    } else {
        echo "Booking failed: " . $bookingResponse['error'];
    }
}

function generateUniqueBarcode()
{
    return strval(mt_rand(10000000, 99999999));
}

function sendBookingRequest($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode)
{
    // Имитируем ответ от API
    $responses = [
        ['message' => 'order successfully booked'],
        ['error' => 'barcode already exists']
    ];
    return $responses[array_rand($responses)];
}

function sendConfirmationRequest($barcode)
{
    // Имитируем ответ от API
    $responses = [
        ['message' => 'order successfully approved'],
        ['error' => 'event cancelled'],
        ['error' => 'no tickets'],
        ['error' => 'no seats'],
        ['error' => 'fan removed']
    ];
    return $responses[array_rand($responses)];
}

function saveOrderToDatabase($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $equal_price)
{
    // Подключение к базе данных
    $pdo = new PDO('mysql:host=localhost;dbname=testdb', 'username', 'password');
    $pdo->beginTransaction(); // Начало транзакции

    try {
        // Сохранение данных заказа
        $stmt = $pdo->prepare("INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, ticket_kid_price, ticket_kid_quantity, barcode, equal_price, created) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, $barcode, $equal_price]);
        
        $pdo->commit(); // Подтверждение транзакции при успешном выполнении
    } catch (Exception $e) {
        $pdo->rollBack(); // Откат транзакции в случае ошибки
        throw $e; // Проброс ошибки для обработки выше
    }
}
