<!doctype html>
<html lang="es">
  <body style="font-family:Arial,Helvetica,sans-serif; color:#222;">
    <h2>¡Reserva confirmada!</h2>
    <p>Hola {{ $payload['guest_name'] }}, tu reservación ha sido confirmada.</p>

    <ul>
      <li><strong>Habitación:</strong> {{ $payload['room_number'] }}</li>
      <li><strong>Entrada:</strong> {{ $payload['check_in'] }}</li>
      <li><strong>Salida:</strong> {{ $payload['check_out'] }}</li>
      <li><strong>Noches:</strong> {{ $payload['nights'] }}</li>
      <li><strong>Total:</strong> ${{ number_format($payload['total_price'],2) }} {{ $payload['currency'] }}</li>
      <li><strong>Código QR para Check‑in:</strong></li>
    </ul>

    <p>
      <img alt="QR Check-in" src="{{ $payload['qr_data_uri'] }}" width="220" height="220">
    </p>

    <p style="font-size:12px;color:#666;">
      Presenta este QR en recepción. Caduca el {{ $payload['qr_expires_at'] }}.
    </p>
  </body>
</html>
