<!-- path: CashFlow/README.md -->
# SRN CashFlow Extension

Extensión para gestión financiera multiempresa con entidades:
- SRNContract
- SRNEmployee
- SRNSalary
- SRNFinancialMovement

Incluye:
- Hook para asignar automáticamente contractType.
- Job programado para recordatorios de vencimiento.
- Panel de configuración (Administración > SRN CashFlow).
- Campos company/contact vinculados correctamente a Account/Contact.
- Filtro dinámico de contactos por empresa (vistas JS personalizadas).

Instalación:
1. Comprimir carpeta `CashFlow` en `SRNCashFlow-1.2.0.zip`.
2. Subir en Admin > Extensions.
3. Verificar que se creó el Scheduled Job y los layouts.