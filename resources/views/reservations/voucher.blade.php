<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Reserva #{{ $res->id }}</title></head>
<body>
  <h1>Confirmación de reserva</h1>
  <p>Huésped: {{ $res->guest_name }} ({{ $res->email }})</p>
  <p>Habitación: {{ $res->room_number }} — {{ $res->room_type }}</p>
  <p>Check-in: {{ $res->check_in }} | Check-out: {{ $res->check_out }}</p>
  <p>Total: ${{ number_format($res->total_price,2) }} MXN</p>
  <p>Estatus: {{ $res->status }}</p>
  <small>Presenta tu QR del correo en recepción.</small>
</body>
</html>
