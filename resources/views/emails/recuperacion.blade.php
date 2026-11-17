<!DOCTYPE html>
<html lang="es">
<body style="font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; color: #0f172a; background: #f8fafc; padding: 24px;">
    <div style="max-width: 520px; margin: 0 auto; background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 24px;">
        <div style="text-align: center; border-bottom: 3px solid #cc783c; padding-bottom: 14px; margin-bottom: 16px;">
            <img src="{{ asset('images/logo-cies-pdf.png') }}" alt="Clínica CIES" style="height: 56px;">
        </div>
        <p>Recibimos una solicitud para recuperar el acceso a tu cuenta.</p>

        @foreach ($enlaces as $e)
            <p style="margin: 18px 0 6px;">Tu usuario es: <strong>{{ $e['usuario'] }}</strong></p>
            <p style="margin: 0 0 12px;">
                <a href="{{ $e['url'] }}" style="display: inline-block; background: #43704f; color: #fff; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600;">
                    Restablecer la contraseña
                </a>
            </p>
        @endforeach

        <p style="color: #64748b; font-size: 13px;">
            El enlace vence en {{ $minutos }} minutos y sirve una sola vez.
            Si no pediste este cambio, ignora este correo: tu contraseña sigue siendo la misma.
        </p>
    </div>
</body>
</html>
