<!-- path: CashFlow/README_JOBS_HOOKS.md -->
# Jobs y Hooks - SRN CashFlow

## Hook
Ubicación: `custom/Espo/Modules/SRNCashFlow/Hooks/SRNContract/BeforeSave.php`  
Función: Determina `contractType` según `isInternal` en las Accounts asociadas.

## Job
Clase: `Espo\Modules\SRNCashFlow\Jobs\SRNContractExpirationReminder`  
Se auto-crea un Scheduled Job (si no existe) para enviar recordatorios de vencimiento por email.

## Rebuild
Después de instalar o actualizar:  
- Admin > Clear Cache  
- Admin > Rebuild  

## Advertencia
El script `BeforeInstall.php` elimina tablas SRN previas (pérdida total de datos en srn_*).