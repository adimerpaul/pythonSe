<?php

use Dompdf\Dompdf;
use Luecano\NumeroALetras\NumeroALetras;

class ClienteController extends Controller
{
    private $cadCon;
    use SqlExecution;
    public function updateFicFlag($tramite){
        //return $tramite;
        $sql = sprintf(
            "select *
            from ficha
                WHERE fic_nros = '%s'",
            $tramite,
        );
        $data = $this->executeSelectSQL($sql);
        return json_encode($data);
    }
    public function getFacturasCliente($cuenta, $isAPI = true)
    {
        $matricula = substr($cuenta, 0, -1);
        $dive = substr($cuenta, -1);

        $sql = sprintf(
            "select TOP 3 PLA_NFAC, 
                IIF(empty(pla_fpag),'pend','pagado') as estado,
pla_nmes as mesesDeuda, pla_cicl as ciclo, pla_nume numeroFacturaImpuestos, PLA_MATR, PLA_DIVE, PUNTCONS.USR_RUCNIT as rucnit, PLA_NFAC as numeroFacturaInterno, PLA_TARI as periodo, PLA_TOTMES as monto, PLA_FPAG as fechaPago, PUNTCONS.USR_EPUN, PUNTCONS.usr_apno as nombre, PUNTCONS.usr_dirr as direccion
                from planilla
                             join puntcons on planilla.pla_matr = puntcons.usr_matr
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s'
                order by planilla.pla_nfac desc",
            $matricula,
            $dive
        );

        $data = $this->executeSelectSQL($sql);
        if (count($data) > 0) {
            $nombreCompleto = $data[0]->nombre;
            $direccion = $data[0]->direccion;
            $rucnit = $data[0]->rucnit;
            $mesesDeuda = $data[0]->mesesdeuda;

            $cliente = [
                "mesesDeuda" => $mesesDeuda,
                "nombre" => $nombreCompleto,
                "direccion" => $direccion,
                "cuenta" => $cuenta,
                "rucnit" => $rucnit
            ];

            return json_encode(["success" => true, "cliente" => $cliente, "facturas" => $data]);
        }
        else{
            return json_encode(["success" => false, "message"  => "La cuenta solicitada no existe"]);
        }
    }

    public function obtenerDeuda($cuenta, $isAPI = true)
    {
        try {
            $matricula = substr($cuenta, 0, -1);
            $dive = substr($cuenta, -1);

            $data = $this->getPlanillaDeuda($matricula, $dive);
            //return json_encode(["success" => false, "message" => $data]);
            $categoria20 = $this->getCategoria20($matricula, $dive);

            $dataTieneMultaMorosidad = $this->getMultaMorosidad($matricula, $dive);

            $dataPresupuesto = $this->getPresupuestoDeuda($cuenta);

            $dataCorte = 0;
            $dataCorte['valor'] = 0;
            if(count($categoria20) == 0){
                $dataCorte = $this->getCorteDeuda($cuenta);

                if (count($dataCorte) > 0){
                    $dataCorte['valor'] = $dataCorte[0]->valor;
                }
            }
            // return json_encode(["corte" => $dataCorte]);
            $otroIngre = [];
            $factus = [];
            if (count($dataTieneMultaMorosidad) > 0  || (count($dataCorte) > 0 && $dataCorte['valor'] > 0 && count($categoria20) == 0) || count($dataPresupuesto) > 0) {
                $cuentas = new CuentasController();
                $montoFormulario = $cuentas->getMontoFormularioOtroIngreso();
                $dataPresupuesto[] = $montoFormulario[0];
            }

            $puntcons = $this->getPuntcons($matricula);

            if (count($dataTieneMultaMorosidad) > 0) {
                $dataPresupuesto[] = $dataTieneMultaMorosidad[0];
            }
            if (count($dataCorte) > 0  && $dataCorte['valor'] > 0 && count($categoria20) == 0) {
                $dataPresupuesto[] = $dataCorte[0];
            }
            if (count($dataPresupuesto) > 0) {
                $otroIngre = $dataPresupuesto;
            }

            if (count($data) > 0) {
                if ($data[0]->pun_caja == 0)
                    return json_encode(["success" => false, "message" => $data[0]->pun_glos]);

                    $nombreCompletoPuntcons = $puntcons[0]->usr_apno;
                    $direccionPuntcons = $puntcons[0]->usr_dirr;
                    $rucnitPuntcons = $puntcons[0]->usr_rucnit;
                    $emailPuntcons = $puntcons[0]->email;
                    $categoriaPuntcons = $puntcons[0]->cat_desc;

                    $cliente = $this->getClientePuntcons($categoriaPuntcons, $emailPuntcons, $nombreCompletoPuntcons, $direccionPuntcons, $cuenta, $rucnitPuntcons);

                $cuentas=new CuentasController();
                $montoFormulario = $cuentas->getMontoFormulario();

                foreach ($data as $fac) {
                    $factu = $this->datosFactura($fac, $montoFormulario);
                    $factus [] = $factu;
                }

                $factura = [
                    "cliente" => $cliente,
                    "facturas" => $factus,
                    "otrosIngresos" => $otroIngre
                ];
                return json_encode(["success" => true, "factura" => $factura]);
            } else
                return json_encode(["success" => false, "message" => "Este número de cuenta no tiene deudas pendientes. Revise el número de cuenta e intente nuevamente"]);
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
    public function datosFactura($data, $montoFormulario){
        return [
            "selected" => $data->selected,
            "pla_nume" => $data->pla_nume,
            "pla_matr" => $data->pla_matr,
            "pla_dive" => $data->pla_dive,
            "usr_rucnit" => $data->usr_rucnit,
            "nrofactura" => $data->nrofactura,
            "periodo" => $data->periodo,
            "total_acumulado" => $data->total_acumulado,
            "monto" =>  $data->monto,
            "pla_redon" => $data->pla_redon,
            "pla_fpag" => $data->pla_fpag,
            "pla_codi" => $data->pla_codi,
            "deuda_mes" => $data->deuda_mes,
            "pla_corte" => $data->pla_corte,
            "usr_otro" => $data->usr_otro,
            "usr_epun" => $data->usr_epun,
            "usr_cort" => $data->usr_cort,
            "usr_apno" => $data->usr_apno,
            "usr_dirr" => $data->usr_dirr,
            "email" => $data->email,
            "pla_cate" => $data->pla_cate,
            "cat_desc" => $data->cat_desc,
            "formulario" => $data->formulario,
            "costoagua" => $data->costoagua,
            "pla_nmes" => $data->pla_nmes,
            "pun_glos" => $data->pun_glos,
            "pla_cicl" => $data->pla_cicl,
            "pun_caja" => $data->pun_caja
        ];
    }
    private function cancelarMulta($datosPagIngre, $cuenta, $nroFactura, $ventanilla, $cuf, $cufd, $leyenda, $cuis, $sucursal, $facturaFinal,$fecha,$cuotas = null,$glosaRequest,$cuenta_selasis=null,$request=null)
    {
        $matricula = substr($cuenta, 0, -1);
        $digito = substr($cuenta, -1);
        $caja_ventanilla = $ventanilla;
        $numero_factura = str_pad($nroFactura, 10, "0", STR_PAD_LEFT);
        $indi= $numero_factura.date('Ymd');


        $dataPresupuesto = $this->getPresupuestoDeuda($cuenta);

        $presupuesto = 0;
        $glosaPresupuesto = "";

        if(count($dataPresupuesto) > 0){
            $glosaPresupuesto = $dataPresupuesto[0]->concepto;
            $presupuesto = $dataPresupuesto[0]->valor;
            $fic_nros = $dataPresupuesto[0]->fic_nros;

            $cuentaItem = $dataPresupuesto[0]->cuenta; //numero
            $concepto= $dataPresupuesto[0]->concepto; //texto
            $total_item = $presupuesto; //numero

            $otroingre= new OtroIngreController();
            $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuentaItem, $concepto, $total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
            if($insertOtroIngre === false)
                $this->restoreMulta($nroFactura, $fecha);
            $sql = sprintf(
                "update ficha set fic_fpre = {ts'%s'}, num_fact = %d, fic_flag = .T. where fic_matr = '%s' and fic_nros = '%s' and fic_flag < 1 and fic_sis = .T.",
                $fecha,
                $nroFactura,
                $cuenta,
                $fic_nros
            );
            $updateFicha = $this->executeCrudSQL($sql);
            if($updateFicha === false)
                $this->restoreMulta($nroFactura, $fecha);

            $sqlUsrOtro = sprintf(
                "UPDATE puntcons SET usr_otro = .F. WHERE usr_matr = '%s' ",
                $matricula
            );
            $updateUsrCort = $this->executeCrudSQL($sqlUsrOtro);
            if($updateUsrCort === false)
                $this->restoreMulta($nroFactura, $fecha);
        }

        $dataTieneMultaMorosidad = $this->getMultaMorosidad($matricula, $digito);
        $multa = 0;
        $glosaMora = "";

        if(count($dataTieneMultaMorosidad) > 0 ){
            $nfacConCorte = $dataTieneMultaMorosidad[0]->pla_nfac;
            $glosaMora = $dataTieneMultaMorosidad[0]->concepto;
            $multa = $dataTieneMultaMorosidad[0]->valor;
            $multaCero = 0.00;

            $cuentaItem = 2213; //numero
            $concepto= $dataTieneMultaMorosidad[0]->concepto; //texto
            $total_item = $multa; //numero
            if($nfacConCorte != $facturaFinal){
                $sqlMovedMulta = sprintf(
                    "UPDATE PLANILLA SET pla_corte = %f WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
                    $multa,
                    $matricula,
                    $digito,
                    $facturaFinal
                );
                $updateMovedMulta = $this->executeCrudSQL($sqlMovedMulta);

                $sqlMultaCero = sprintf(
                    "UPDATE PLANILLA SET pla_corte = %f WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
                    $multaCero,
                    $matricula,
                    $digito,
                    $nfacConCorte
                );
                $updateMultaCero = $this->executeCrudSQL($sqlMultaCero);


                $sqlEliminarMulta = sprintf(
                    "UPDATE PLANILLA SET pla_totmes = pla_totmes - $multa, pla_totacu = pla_totacu - $multa WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
                    $matricula,
                    $digito,
                    $nfacConCorte
                );
                $eliminarMulta = $this->executeCrudSQL($sqlEliminarMulta);
            }

            $otroingre= new OtroIngreController();
            $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuentaItem, $concepto, $total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
            if($insertOtroIngre === false)
                $this->restoreMulta($nroFactura, $fecha);
            $sqlUsrOtro = sprintf(
                "UPDATE puntcons SET usr_otro = .F. WHERE usr_matr = '%s' ",
                $matricula
            );
            $updateUsrCort = $this->executeCrudSQL($sqlUsrOtro);
            if($updateUsrCort === false)
                $this->restoreMulta($nroFactura, $fecha);

        }
        $dataCorte['valor'] = 0;
        $categoria20 = $this->getCategoria20($matricula, $digito);
        $dataCorte = $this->getCorteDeuda($cuenta);
        if(count($dataCorte) > 0){
            $dataCorte['valor'] = $dataCorte[0]->valor;
        }
        $corte = 0;
        $glosaCorte = "";

        if(count($dataCorte) > 0 && count($categoria20) == 0){
            $nfac = $dataCorte[0]->nfac;
            $glosaCorte = $dataCorte[0]->concepto;
            $corte = $dataCorte[0]->valor;

            $cuentaItem = $dataCorte[0]->cuenta; //numero
            $concepto= $dataCorte[0]->concepto; //texto
            $total_item = $corte; //numero

            if($dataCorte[0]->valor > 0){
                $otroingre= new OtroIngreController();
                $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuentaItem, $concepto, $total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
                if($insertOtroIngre === false)
                    $this->restoreMulta($nroFactura, $fecha);
                $sql = sprintf(
                    "UPDATE corte SET cor_monto = %f, num_fact = %d, cor_fpag = {ts'$fecha'}, cor_nren = '%s' WHERE cor_matr = '%s' and empty(cor_fpag) and !empty(cor_freal) and cor_nfac = %d ",
                    $corte,
                    $nroFactura,
                    $numero_factura,
                    $cuenta,
                    $nfac
                );
                $updateCorte = $this->executeCrudSQL($sql);
                if($updateCorte === false)
                    $this->restoreMulta($nroFactura, $fecha);

                $sqlUsrCort = sprintf(
                    "UPDATE puntcons SET usr_cort = 'R', usr_otro = .F. WHERE usr_matr = '%s' ",
                    $matricula
                );
                $updateUsrCort = $this->executeCrudSQL($sqlUsrCort);
                if($updateUsrCort === false)
                    $this->restoreMulta($nroFactura, $fecha);

            }

        }

        $creditosMonto=0;
        $codigo = "";
        if ($cuotas!=null) {
            $creditoController = new CreditoController();
            $cobrarOtroIngreso = false;
            $interes = 0;
            foreach ($cuotas as $index=>$cuota) {
                if ($cuota['selected']==true){
                    $cobrarOtroIngreso = true;
                    $codigo = $cuota['codigo'];
                    $totalCalculado=($cuota['amortizacion']) + ($cuota['interes_pagado_respaldo']);
                    $creditosMonto += round($cuota['amortizacion'],2);
                    $cabecera['cuo_amor'] = $cuota['amortizacion'];
                    $cabecera['cuo_inte'] = $cuota['interes_pagado_respaldo'];
                    $cabecera['cuo_tpag'] = $totalCalculado;
                    $cabecera['cuo_nue'] = $cuota['codigo_credito'];
                    $creditoController->insertCuotaCancelado($cuota['codigo'], $cabecera, $numero_factura, $cuenta_selasis==null?0:intval($cuenta_selasis));
                    $otroingre= new OtroIngreController();
                    if ($index==0 && $cuota['interes_pagado_respaldo']>0){
                        $interes = $cuota['interes_pagado_respaldo'];
                        $cuentas=new CuentasController();
                        $cuenta = 2218; //numero
                        $concepto = $cuentas->searchCuenta($cuenta)->nombre; //texto
                        $total_item = $cuota['interes_pagado_respaldo']; //numero
                        $creditosMonto += round($cuota['interes_pagado_respaldo'],2);
                        $otroingre->insertOtroIngre($matricula, $digito, $cuenta, $concepto,$total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
                    }
                }
            }
            if ($cobrarOtroIngreso){
                $monto = $creditosMonto-$interes;
                $otroingre->insertOtroIngre($matricula, $digito, $cabecera['cuo_nue'], $cuota['concepto'], $monto, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);

                $sql = sprintf("update crediotros set cre_sald=cre_sald-$creditosMonto,cre_samo=cre_samo+$creditosMonto  where cre_nusr = %d", $cuotas[0]['codigo']);
                $this->executeCrudSQL($sql);

                // actualizar cre_flag
                $sql = sprintf("select * from crediotros where cre_nusr = %d", $cuotas[0]['codigo']);
                $sqlCredito = $this->executeSelectSQL($sql);
                $saldoCredito=trim($sqlCredito[0]->cre_sald);
                if ($saldoCredito<=0){
                    $sql = sprintf("update crediotros set cre_flag=0 where cre_nusr = %d", $cuotas[0]['codigo']);
                    $this->executeCrudSQL($sql);
                }
            }

        }
//        error_log("request: ".json_encode($request));
        $montoSancion = 0;
        if ($request['codigoSancion']!=null){
            $cuentas=new CuentasController();
            $cuenta = 2211; //numero
            $concepto = $cuentas->searchCuenta($cuenta)->nombre; //texto
            $total_item = $request['montoSancion']; //numero
            $otroingre= new OtroIngreController();
            $montoSancion = $request['montoSancion'];
            $otroingre->insertOtroIngre($matricula, $digito, $cuenta, $concepto,$total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
            error_log('request: '.json_encode($request));
            if ($request['tipoSancion']=="SancionCuota"){
                $codigoSancion = $request['codigoSancion'];
                $sqlChangeSanCouta=sprintf("update sanciones set san_cuota=san_cuota+%d, san_pcon=san_pcon+1 where san_nros=%d",$montoSancion,$codigoSancion);
                error_log('sqlChangeSanCouta: '.$sqlChangeSanCouta);
                $this->executeSelectSQL($sqlChangeSanCouta);
            }
        }

        if(
            count($dataTieneMultaMorosidad) > 0  ||
            (count($dataCorte) > 0 && $dataCorte['valor'] > 0 && count($categoria20) == 0) ||
            count($dataPresupuesto) > 0 ||
            $creditosMonto > 0 ||
            $request['codigoSancion']!=null
        )
        {
            $cuentas=new CuentasController();
            $montoFormulario = $cuentas->getMontoFormulario();
            $total = $multa + $corte + $montoFormulario + $presupuesto+$creditosMonto+$montoSancion;
            $nombre = $datosPagIngre->usr_apno;
            $direccion = $datosPagIngre->usr_dirr;
            if(count($dataCorte) > 0) {
                $glosa = $glosaCorte;
            }elseif (count($dataTieneMultaMorosidad) > 0) {
                $glosa = $glosaMora;
            }elseif (count($dataPresupuesto) > 0){
                $glosa = $glosaPresupuesto;
            }elseif ($creditosMonto > 0){
                $glosa = $glosaRequest;
            }elseif ($montoSancion > 0){
                $glosa = $request['otroIngreso']['glosa'];
            }
            $rucnit= $datosPagIngre->usr_rucnit;
            $codigo_control = "";
            $control_interno = 0;
            $es_factura = 0;
            $url = "";
            $se_valido = 0;
            $es_anu_imp = 0;

            $email = "";
            $pagingre=new PagingreController();

            $insertPagIngre = $pagingre->insertPagIngre($matricula, $digito,$codigo, $nombre, $direccion , $rucnit, $caja_ventanilla, $glosa, $total, $fecha, $numero_factura, $codigo_control, $control_interno, $sucursal, $indi, $cuf, $cufd, $es_factura, $url, $se_valido, $es_anu_imp,$leyenda,$cuis, $numero_factura, $email);
//            return json_encode(['success'=>false,'message'=>$insertPagIngre]);
            if($insertPagIngre === false)
                $this->restoreMulta($nroFactura, $fecha);
            $cuenta = 2216; //numero
            $concepto = "REPOSICION DE FACTURA"; //texto
            $total_item = $montoFormulario; //numero
            $caja_ventanilla = $ventanilla; //numero

            $insertOtroIngre =  $otroingre->insertOtroIngre($matricula, $digito, $cuenta, $concepto, $total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
//            return json_encode(['success'=>false,'message'=>$insertOtroIngre]);
            if($insertOtroIngre === false)
                $this->restoreMulta($nroFactura, $fecha);

        }
    }

    /**
     * @param $factura
     * @param array $detalles
     * @return array
     */
    public function addDetail($factura, array $detalles, $pagSer, $tipo = 'Nuevo'): array
    {
        $fechaInicio = date("Y-m-d H:i:s", strtotime("$factura->pla_femi"));
        $fechaInicio1 = strtotime('+8 hour', strtotime($fechaInicio));
        $fechaInicio2 = date("Y-m-d H:i:s", $fechaInicio1);

        $fechaFin = $factura->pla_femi;

        if(intval($factura->pla_tari) >= 202204){
            $sqlRentahis = sprintf(
                "select ren_flim, ren_orden, ren_feini, ren_fefin, ren_art
                    from rentahis1
                    WHERE ren_feini <= {ts'%s'} and ren_fefin >= {ts'%s'}
                   ", $fechaInicio2, $fechaFin
            );
            $renta = $this->executeSelectSQL($sqlRentahis);
        }
        else{
            $sqlRentahis = sprintf(
                "select ren_flim, ren_orden, ren_feini, ren_fefin, ren_art
                    from rentahis
                    WHERE ren_feini <= {ts'%s'} and ren_fefin >= {ts'%s'}
                   ", $fechaInicio2, $fechaFin
            );
            $renta = $this->executeSelectSQL($sqlRentahis);
        }
        $nroFactura = $factura->num_fact;
        if(empty($factura->cuf) || $factura->cuf == null){
            $nroFactura = $factura->pla_nume;
        }

        $orden = $renta[0]->ren_orden;
        $flim = $renta[0]->ren_flim;
        $leyendaAnterior = $renta[0]->ren_art;
        return [
            "correlativoDiaCajero" => $pagSer,
            "renta" => $orden,
            "flim" => $flim,
            "ley1886" => $factura->pla_leyprv,
            "abono" => $factura->pla_abono,
            "formulario" => $factura->pla_form,
            "costoAgua" => $factura->pla_totmes - $factura->pla_form,
            "codigoControl" => $factura->pla_ccon,
            "servicioAgua" => $factura->pla_vlag,
            "fechaVencimiento" => $factura->pla_fven,
            "leyenda" => empty($factura->leyenda)? $leyendaAnterior : $factura->leyenda,
            "nfac" => $factura->pla_nfac,
            "consumoMes" => $factura->pla_cns1,
            "descripcionConsumo" => $factura->con_desc,
            "tarifa" => $factura->pla_tari,
            "total" => $factura->pla_vlpg,
            "lecturaActual" => $factura->pla_lec1,
            "lecturaAnterior" => $factura->pla_lec2,
            "fechaLecturaActual" => $factura->pla_flec1,
            "fechaLecturaAnterior" => $factura->pla_flec2,
            "mesesDeuda" => $factura->pla_nmes,
            "cuf" => $factura->cuf,
            "nroFactura" => $nroFactura,
            "pla_nume" => $factura->pla_nume,
            "fecha" => substr($factura->pla_femi,0,10),
            "es_conci" => $factura->es_conci,
            "mon_conci" => $factura->mon_conci,
            "pla_codi" => $factura->pla_codi,
            "tipo" => $tipo,
            "categoria" => $factura->cat_desc,
            "pla_nan1" => $factura->pla_nan1,
            //"fechaDuplicacion" => $factura->imp_fpag,
            "detalle" => $detalles
        ];
    }

    /**
     * @param $matricula
     * @param $dive
     * @return array|void
     */
    public function getPlanillaDeuda($matricula, $dive)
    {
        $sql = sprintf(
            "select 1 as selected, pla_nume , PLA_MATR, PLA_DIVE, PUNTCONS.USR_RUCNIT, PLA_NFAC as nroFactura, PLA_TARI as periodo, 
       PLA_TOTACU as total_acumulado, (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as monto, PLA_REDON, PLA_FPAG, PLA_CODI, 
       (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as DEUDA_MES, 
       PLA_CORTE, PUNTCONS.USR_OTRO, PUNTCONS.USR_EPUN, PUNTCONS.USR_CORT, PUNTCONS.usr_apno, PUNTCONS.usr_dirr, puntcons.email, pla_cate, cat_desc,
       pla_form as formulario, (pla_vlag + pla_vlma + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon - pla_form) as costoAgua, pla_nmes,  
       pun_glos, pla_cicl, pun_caja, pla_totmes
                from planilla
                    join categori on planilla.pla_cate = categori.usr_cate   
                    join puntcons on planilla.pla_matr = puntcons.usr_matr
                    join estapunt on puntcons.usr_epun = estapunt.usr_epun
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(PLA_FPAG) and pla_codi != 8
                order by planilla.pla_nfac asc",
            $matricula,
            $dive
        );

        $data = $this->executeSelectSQL($sql);
        return $data;
    }

    public function getCategoria20($matricula, $dive)
    {
        $sql = sprintf(
            "select USR_MATR, USR_DIVE, USR_CATE 
                from puntcons
                WHERE USR_MATR = '%s' and USR_DIVE = '%s' and usr_cate = 20
                ",
            $matricula,
            $dive
        );

        $data = $this->executeSelectSQL($sql);
        return $data;
    }

    public function getPlanillaDeudaAntiguos($matricula, $dive)
    {
        $sql = sprintf(
            "select 1 as selected, pla_nume , PLA_MATR, PLA_DIVE, PUNTCONS.USR_RUCNIT, PLA_NFAC as nroFactura, PLA_TARI as periodo, 
       PLA_TOTACU as total_acumulado, (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as monto, PLA_REDON, PLA_FPAG, PLA_CODI, 
       (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as DEUDA_MES, 
       PLA_CORTE, PUNTCONS.USR_OTRO, PUNTCONS.USR_EPUN, PUNTCONS.USR_CORT, PUNTCONS.usr_apno, PUNTCONS.usr_dirr, puntcons.email, pla_cate, cat_desc,
       pla_form as formulario, (pla_vlag + pla_vlma + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon - pla_form) as costoAgua, pla_nmes,  
       pun_glos, impuestos.es_conci, impuestos.mon_conci, impuestos.num_fact, pla_cicl, pun_caja
                from planilla
                    join impuestos on planilla.pla_matr = impuestos.imp_matr and planilla.pla_nfac = impuestos.imp_nfac
                    join categori on planilla.pla_cate = categori.usr_cate   
                    join puntcons on planilla.pla_matr = puntcons.usr_matr
                    join estapunt on puntcons.usr_epun = estapunt.usr_epun
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(PLA_FPAG)
                order by planilla.pla_nfac asc",
            $matricula,
            $dive
        );

        $data = $this->executeSelectSQL($sql);
        return $data;
    }

    /**
     * @param $cuenta
     * @return array|void
     */
    public function getPresupuestoDeuda($cuenta)
    {
        $sqlPresupuesto = sprintf(
            "select
                        top 1
                       fic_tota as valor,
                       cuentas.detalle as concepto,
                       ins_cuen as cuenta,
                       'tramites' as tipo,
                       fic_nros,
                       fic_tipo
                from ficha
                    join tipoinsta on ficha.fic_tipo = tipoinsta.ins_codi
                    join cuentas on tipoinsta.ins_cuen = cuentas.cuenta
                WHERE fic_tota > 0 and fic_flag < 1 and fic_sis = .T. and fic_matr = '%s'
                and (fic_tipo != 7 or fic_tipo != 8) and fic_tipo < 24
                order by fic_nros asc
                ",
            $cuenta,
        );
        return $this->executeSelectSQL($sqlPresupuesto);
    }


    public function getMultaMorosidad($matricula, $dive)
    {

        $sqlTieneMultaMorosidad = sprintf(
            "select pla_corte as valor, 'MULTAS POR MOROSIDAD' as concepto, '2213' as cuenta, pla_nfac
                from planilla
                join puntcons on planilla.pla_matr = puntcons.usr_matr
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(PLA_FPAG) and pla_corte > 0
                order by planilla.pla_nfac desc",
            $matricula,
            $dive
        );
        return  $this->executeSelectSQL($sqlTieneMultaMorosidad);

    }


    /**
     * @param $cuenta
     * @return array|void
     */
    public function getCorteDeuda($cuenta)
    {
        $sqlCorte = sprintf(
            "select 
                        tip_valor as valor,
                        cuentas.nombre as concepto,
                        tip_cuen as cuenta,
                        'corte' as tipo,
                        cor_nfac as nfac
                from corte
                    join tipocorte on corte.cor_codg = tipocorte.tip_codi
                    join cuentas on tipocorte.tip_cuen = cuentas.cuenta  
                WHERE cor_matr = '%s' and !empty(cor_freal) and empty(cor_fpag) and empty(cor_freha)
                order by cor_nfac desc
                ",
            $cuenta
        );
        $dataCorte = $this->executeSelectSQL($sqlCorte);
        return $dataCorte;
    }

    /**
     * @param $facturas
     * @param $matricula
     * @param $digito
     * @return array
     */
    public function getCliente($facturas, $matricula, $digito): array
    {
        $cliente = [
            "razon_social" => $facturas->usr_apno,
            "direccion" => $facturas->usr_dirr,
            "cuenta" => $matricula . $digito,
            "ci" => $facturas->pla_rucnit,
            "circuito" => $facturas->usr_circ . $facturas->usr_cdig . $facturas->usr_cdi1,
            "medidor" => $facturas->usr_numm,
            "catastro" => $facturas->usr_cicl . $facturas->usr_sect . $facturas->usr_ruta . $facturas->usr_manz . $facturas->usr_rloc,
            "actividad" => $facturas->tac_desc,
            "ventanilla" => $facturas->pla_intcre
        ];
        return $cliente;
    }
    public function getClienteSoloOtroIngre($facturas, $matricula, $digito, $ventanilla): array
    {
        $cliente = [
            "razon_social" => $facturas->usr_apno,
            "direccion" => $facturas->usr_dirr,
            "cuenta" => $matricula . $digito,
            "ci" => $facturas->usr_rucnit,
            "circuito" => $facturas->usr_circ . $facturas->usr_cdig . $facturas->usr_cdi1,
            "medidor" => $facturas->usr_numm,
            "catastro" => $facturas->usr_cicl . $facturas->usr_sect . $facturas->usr_ruta . $facturas->usr_manz . $facturas->usr_rloc,
            "actividad" => $facturas->tac_desc,
            "categoria" => $facturas->cat_desc,
            "ventanilla" => $ventanilla
        ];
        return $cliente;
    }

    /**
     * @param $matricula
     * @return array|false
     */
    public function getPuntcons($matricula)
    {
        $sqlPuncons = sprintf("select usr_apno, usr_dirr, usr_rucnit, email, cat_desc, usr_numm
                                            from puntcons
                                            join categori on puntcons.usr_cate = categori.usr_cate  
                                            where usr_matr = '%s'",
            $matricula);
        $puntcons = $this->executeSelectSQL($sqlPuncons);
        return $puntcons;
    }

    /**
     * @param $categoriaPuntcons
     * @param $emailPuntcons
     * @param $nombreCompletoPuntcons
     * @param $direccionPuntcons
     * @param $cuenta
     * @param $rucnitPuntcons
     * @return array
     */
    public function getClientePuntcons($categoriaPuntcons, $emailPuntcons, $nombreCompletoPuntcons, $direccionPuntcons, $cuenta, $rucnitPuntcons): array
    {
        $cliente = [
            "categoria" => $categoriaPuntcons,
            "email" => $emailPuntcons,
            "nombre" => $nombreCompletoPuntcons,
            "direccion" => $direccionPuntcons,
            "cuenta" => $cuenta,
            "rucnit" => $rucnitPuntcons
        ];
        return $cliente;
    }

    /**
     * @param $factura
     * @return mixed
     */
    public function getCorrelativoCajero($factura)
    {
        $sqlPagos = sprintf(
            "select 
                        pag_ser
                    from pagos
                    WHERE pag_matr = '%s' and pag_nfac = %d and pag_esta = 'C'
                                ", $factura->usr_matr, $factura->pla_nfac
        );
        $pagos = $this->executeSelectSQL($sqlPagos);
        $pagSer = $pagos[0]->pag_ser;
        return $pagSer;
    }

    /**
     * @param $factura
     * @param array $detalles
     * @return array
     */
    public function getDetallesFactura($factura, array $detalles): array
    {
        if ($factura->pla_codabo == 30 || $factura->pla_codabo == 34 || $factura->pla_codabo == 43) {
            $detalles [] = [
                "codigoProducto" => "411030",
                "descripcion" => "Cargo",
                "precioUnitario" => $factura->pla_abono,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        if ($factura->pla_codabo == 20 || $factura->pla_codabo == 24 || $factura->pla_codabo == 26 || $factura->pla_codabo == 31) {
            $detalles [] = [
                "codigoProducto" => "411020",
                "descripcion" => "Abono",
                "precioUnitario" => $factura->pla_abono,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        if ($factura->pla_vlag > 0) {
            $detalles [] = [
                "codigoProducto" => "411004",
                "descripcion" => "Consumo Mes",
                "precioUnitario" => $factura->pla_vlag,
                "tarifa" => $factura->pla_tari,
                "cantidad" => $factura->pla_cns1

            ];
        }
        if ($factura->pla_vlma > 0) {
            $detalles [] = [
                "codigoProducto" => "411003",
                "descripcion" => "Cargo Fijo",
                "precioUnitario" => $factura->pla_vlma,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        if ($factura->pla_form > 0) {
            $detalles [] = [
                "codigoProducto" => "2216",
                "descripcion" => "Formulario",
                "precioUnitario" => $factura->pla_form,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        if ($factura->pla_inag < 0) {
            $detalles [] = [
                "codigoProducto" => "411030",
                "descripcion" => $factura->pla_cate == 21 ? "Social Solidario" : "Abono",
                "precioUnitario" => $factura->pla_inag,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        if ($factura->pla_leyprv != 0) {
            $detalles [] = [
                "codigoProducto" => "2225",
                "descripcion" => "Ley de  Privilegio",
                "precioUnitario" => $factura->pla_leyprv,
                "tarifa" => $factura->pla_tari,
                "cantidad" => 1
            ];
        }
        return $detalles;
    }

    /**
     * @param $fecha_pago
     * @param $fechaInicio
     * @param $fechaFin
     * @param $nroFactura
     * @return array|false
     */
    public function getPagOtroIngre($fecha_pago, $fechaInicio, $fechaFin, $nroFactura)
    {
        $sql = sprintf(
            "select 
                    pag_glos,
                    pag_rucnit,
                    pag_direc,
                    pag_nren,
                    otr_nren,
                    pag_matr,
                    pag_dive,
                    pag_apno,
                    pag_valo,
                    pag_fpag,
                    pag_banc,
                    pag_esta,
                    es_factura,
                    cuf,
                    cufd,
                    otr_fpag,
                    otr_conc,
                    otr_vlpg,
                    otr_nue,
                    pagingre.num_fact,
                    leyenda,
                    pagingre.pag_ccon,
                    pag_fpag1,
                    pag_ctra
        from pagingre
        join otroingre on pagingre.pag_nren = otroingre.otr_nren
        WHERE pag_fpag between {ts'%s'} and {ts'%s'} and otr_fpag > {ts'%s'} and otr_fpag < {ts'%s'} and pagingre.num_fact = %d 
        ", $fechaInicio, $fechaFin, $fechaInicio, $fechaFin, $nroFactura
        );
        $facturaOtroIngreso = $this->executeSelectSQL($sql);
        return $facturaOtroIngreso;
    }

    /**
     * @param $facturaOtroIngreso
     * @param array $detallesOtroIngreso
     * @param array $facturasCanceladas
     * @return array
     */
    public function getFacturaOtroIngreso($matricula, $facturaOtroIngreso, array $detallesOtroIngreso, array $facturasCanceladas): array
    {
        $puntcons = $this->getPuntcons($matricula);

        foreach ($facturaOtroIngreso as $detalleOtro) {
            $detallesOtroIngreso [] = [
                "codigoProducto" => $detalleOtro->otr_nue,
                "descripcion" => $detalleOtro->otr_conc,
                "precioUnitario" => $detalleOtro->otr_vlpg,
                "tarifa" => null,
                "cantidad" => 1
            ];
        }

        $detallesFactura = $detallesOtroIngreso;

        $facturasCanceladas [] = [
            "categoria" => $puntcons[0]->cat_desc,
            "renta" => null,
            "flim" => null,
            "formulario" => null,
            "costoAgua" => null,
            "codigoControl" => $facturaOtroIngreso[0]->pag_ccon,
            "servicioAgua" => null,
            "fechaVencimiento" => null,
            "leyenda" => $facturaOtroIngreso[0]->leyenda,
            "consumo" => null,
            "lecturaActual" => null,
            "lecturaAnterior" => null,
            "fechaLecturaActual" => null,
            "fechaLecturaAnterior" => null,
            "mesesDeuda" => null,
            "nfac" => null,
            "tarifa" => null,
            "total" => $facturaOtroIngreso[0]->pag_valo,
            "cuf" => $facturaOtroIngreso[0]->cuf,
            "nroFactura" => $facturaOtroIngreso[0]->num_fact,
            "fecha" => date("Y-m-d", strtotime($facturaOtroIngreso[0]->pag_fpag)),
            "detalle" => $detallesFactura
        ];
        return $facturasCanceladas;
    }

    /**
     * @param $facturasAntiguas
     * @param array $nfacsAntiguos
     * @param $fechaPago
     * @param $ventanilla
     * @param $matricula
     * @param $digito
     * @param $fechaHora
     * @param $sucursal
     * @param $cuf
     * @param $cufd
     * @param $leyenda
     * @param $cuis
     * @return array
     */
    public function insertFacturasAntiguas($facturasAntiguas, array $nfacsAntiguos, $fechaPago, $ventanilla, $matricula, $digito, $fechaHora, $sucursal, $cuf, $cufd, $leyenda, $cuis, $fecha)
    {
        foreach ($facturasAntiguas as $facturas) {
            $nfac = $facturas['nfac'];

            $nfacsAntiguos [] = $nfac;

            $monto = $facturas['monto'];
            $cuf = $facturas['cuf'];
            $cufd = $facturas['cufd'];
            $nroFacturaAntiguo = $facturas['nroFactura'];
            $indi = $nroFacturaAntiguo . date('Ymd');
            $fechaHoraAntiguo = $facturas['fecha'];
            $leyenda = $facturas['leyenda'];
            $formulario = $facturas['formulario'];
            //return json_encode(["monto" => $indi]);

            $codi = 1;//codi siempre en 1?
            $updatePlanilla = $this->actualizarPlanillaAntiguo($nroFacturaAntiguo, $fechaPago, $ventanilla, $codi, $matricula, $digito, $nfac, $monto, $leyenda, $cuf, $cufd);
            // return $updatePlanilla;
            $updatePlaAnt = $this->actualizarPlaAntAntiguo($matricula, $digito, $nfac, $monto);
            $cuentaAgua = "411000"; //numero
            $cuentaFormualrio = "2216"; //numero
            $conceptoAgua = "CONSUMO AGUA POTABLE"; //texto
            $conceptoFormulario = "REPOSICION DE FACTURA"; //texto
            $totalItemAgua = $monto - $formulario; //numero
            $montoFormulario = $formulario;
            $estado = 'D';
            $controlInterno = $this->getControlInternoCajero($fechaPago, $ventanilla);
            $this->insertarPagos($matricula, $ventanilla, $digito, $nfac, $monto, $fechaPago, $nroFacturaAntiguo, '', $controlInterno, $sucursal);
            //return $montoFormulario;
            $otroingre = new OtroIngreController();
            $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuentaAgua, $conceptoAgua, $totalItemAgua, $fechaHoraAntiguo, $ventanilla, $nroFacturaAntiguo, $sucursal, $indi, $nroFacturaAntiguo, $estado);

            $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuentaFormualrio, $conceptoFormulario, $montoFormulario, $fechaHoraAntiguo, $ventanilla, $nroFacturaAntiguo, $sucursal, $indi, $nroFacturaAntiguo, $estado);

            $puntcons = $this->getPuntcons($matricula);

            $nombre = $puntcons[0]->usr_apno;
            $direccion = $puntcons[0]->usr_dirr;
            $rucnit = $puntcons[0]->usr_rucnit;
            $emailPuntcons = $puntcons[0]->email;
            $categoriaPuntcons = $puntcons[0]->cat_desc;
            $codigo_control = "";
            $url = "";
            $se_valido = 0;
            $control_interno = 0;
            $es_factura = 0;
            $codigo = "";
            $email = "";
            $es_anu_imp = 0;
            $pagingre = new PagingreController();
            $insertPagIngre = $pagingre->insertPagIngre($matricula, $digito, $codigo, $nombre, $direccion, $rucnit, $ventanilla, $conceptoAgua, $monto, $fechaHoraAntiguo, $nroFacturaAntiguo, $codigo_control, $control_interno, $sucursal, $indi, $cuf, $cufd, $es_factura, $url, $se_valido, $es_anu_imp, $leyenda, $cuis, $nroFacturaAntiguo, $email, $estado);
            //return $insertPagIngre;
        }
        return ($nfacsAntiguos);
    }

    /**
     * @param $planilla
     * @param $fechaPago
     * @param $ventanilla
     * @param $matricula
     * @param $digito
     * @param $sucursal
     * @param string $facturasComa
     * @param $fechaHora
     * @return mixed
     */
    public function cancelacionFacturasNormales($planilla, $fechaPago, $ventanilla, $matricula, $digito, $sucursal, string $facturasComa, $fechaHora)
    {
        foreach ($planilla as $pla) {

            $codi = 1;
            if ($pla->pla_codi == 4) {
                $codi = 1;
            }

            if ($pla->pla_codi == 1) {
                $codi = 1;
            }

            if ($pla->pla_codi == 9) {
                $codi = 10;
            }

            if ($pla->pla_codi == 5) {
                $codi = 3;
            }

            $totalMes = $pla->pla_vlag + $pla->pla_vlma + $pla->pla_form + $pla->pla_leyprv + $pla->pla_abono + $pla->pla_inag + $pla->pla_cuocre + $pla->pla_redon;
            $totacu = $pla->pla_totacu;
            $nrem = $pla->pla_nume;
            $ccon = $pla->pla_ccon;
            $factura = $pla->pla_nfac;
            $controlInterno = $this->getControlInternoCajero($fechaPago, $ventanilla);

            $sqlPagos = sprintf(
                "select pag_matr, pag_dive, pag_banc, pag_nfac
                        from pagos
                        WHERE pag_matr = '%s' and pag_dive = '%s' and pag_nfac = %d and pag_esta = 'C'",
                $matricula,
                $digito,
                $factura
            );
            $pagos = $this->executeSelectSQL($sqlPagos);

            if(count($pagos) == 0) {
                $insertPagos = $this->insertarPagos($matricula, $ventanilla, $digito, $factura, $totalMes, $fechaPago, $nrem, $ccon, $controlInterno, $sucursal);
                if ($insertPagos === false)
                    $this->restoreFacturas($matricula, $facturasComa);
            }

            $updatePlanilla = $this->actualizarPlanilla($fechaHora, $fechaPago, $ventanilla, $codi, $matricula, $digito, $factura, $totalMes);
            if ($updatePlanilla === false)
                $this->restoreFacturas($matricula, $facturasComa);
            $updatePlaAnt = $this->actualizarPlaAnt($matricula, $digito, $factura, $totalMes);
            if ($updatePlaAnt === false)
                $this->restoreFacturas($matricula, $facturasComa);
        }
        return $factura;
    }

    /**
     * @param $matricula
     * @return array|false
     */
    public function getDatosPagIngre($matricula)
    {
        $sqldatosPagIngre = sprintf("select usr_apno, usr_dirr, usr_rucnit,usr_circ,
                                                    usr_cdig,
                                                    usr_cdi1,
                                                    usr_numm,
                                                    usr_cicl,
                                                    usr_sect,
                                                    usr_ruta,
                                                    usr_manz,
                                                    usr_rloc,
                                                    tac_desc,
                                                    cat_desc
                                            from puntcons
                                            join categori on puntcons.usr_cate = categori.usr_cate
                                            join tipactiv on puntcons.usr_tiac = tipactiv.usr_tact
                                            where usr_matr = '%s'", $matricula);
        $datosPagIngre = $this->executeSelectSQL($sqldatosPagIngre);
        return $datosPagIngre;
    }

    /**
     * @param $fechaPago
     * @param $ventanilla
     * @return int
     */
    public function getControlInternoCajero($fechaPago, $ventanilla): int
    {
        $sqlCountInterno = "select top 1 * from pagos where pag_fpag = {d'$fechaPago'} and pag_banc = $ventanilla order by pag_ser desc";
        $control = $this->executeSelectSQL($sqlCountInterno);
        if (count($control) > 0) {
            $controlInterno = $control[0]->pag_ser + 1;
        } else {
            $controlInterno = 1;
        }
        return $controlInterno;
    }

    /**
     * @param int $multa
     * @param int $corte
     * @param int $presupuesto
     * @param $datosPagIngre
     * @param array $dataCorte
     * @param string $glosaCorte
     * @param $dataTieneMultaMorosidad
     * @param string $glosaMora
     * @param array|null $dataPresupuesto
     * @param string $glosaPresupuesto
     * @param $matricula
     * @param $digito
     * @param $caja_ventanilla
     * @param $fecha
     * @param string $numero_factura
     * @param $sucursal
     * @param string $indi
     * @param $cuf
     * @param $cufd
     * @param $leyenda
     * @param $cuis
     * @param $nroFactura
     * @param $ventanilla
     * @param OtroIngreController $otroingre
     * @param int $cuenta
     * @return void
     */
    public function insertPagIngreFormulario(int $multa, int $corte, int $presupuesto, $datosPagIngre, array $dataCorte, string $glosaCorte, $dataTieneMultaMorosidad, string $glosaMora, ?array $dataPresupuesto, string $glosaPresupuesto, $matricula, $digito, $caja_ventanilla, $fecha, string $numero_factura, $sucursal, string $indi, $cuf, $cufd, $leyenda, $cuis, $nroFactura, $ventanilla, OtroIngreController $otroingre, int $cuenta): void
    {
        $cuentas = new CuentasController();
        $montoFormulario = $cuentas->getMontoFormulario();
        $total = $multa + $corte + $montoFormulario + $presupuesto;
        $nombre = $datosPagIngre->usr_apno;
        $direccion = $datosPagIngre->usr_dirr;
        if (count($dataCorte) > 0) {
            $glosa = $glosaCorte;
        } elseif (count($dataTieneMultaMorosidad) > 0) {
            $glosa = $glosaMora;
        } elseif (count($dataPresupuesto) > 0) {
            $glosa = $glosaPresupuesto;
        }
        $rucnit = $datosPagIngre->usr_rucnit;
        $codigo_control = "";
        $control_interno = 0;
        $es_factura = 0;
        $url = "";
        $se_valido = 0;
        $es_anu_imp = 0;
        $codigo = "";
        $email = "";
        $pagingre = new PagingreController();

        $insertPagIngre = $pagingre->insertPagIngre($matricula, $digito, $codigo, $nombre, $direccion, $rucnit, $caja_ventanilla, $glosa, $total, $fecha, $numero_factura, $codigo_control, $control_interno, $sucursal, $indi, $cuf, $cufd, $es_factura, $url, $se_valido, $es_anu_imp, $leyenda, $cuis, $numero_factura, $email);
        if ($insertPagIngre === false)
            $this->restoreMulta($nroFactura, $fecha);
        $cuenta = 2216; //numero
        $concepto = "REPOSICION DE FACTURA"; //texto
        $total_item = $montoFormulario; //numero
        $caja_ventanilla = $ventanilla; //numero


        $insertOtroIngre = $otroingre->insertOtroIngre($matricula, $digito, $cuenta, $concepto, $total_item, $fecha, $caja_ventanilla, $numero_factura, $sucursal, $indi, $nroFactura);
        if ($insertOtroIngre === false)
            $this->restoreMulta($nroFactura, $fecha);
    }

    private function tieneCorte($matricula, $dive)
    {
        $tieneCorteSql = sprintf(
            "select PLA_CORTE
                from planilla
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(PLA_FPAG) and PLA_CORTE > 0",
            $matricula,
            $dive
        );
        return $dataCorte = $this->executeSelectSQL($tieneCorteSql);
    }

    public function actualizarPlanillaAntiguo($nroFacturaAntiguo, $fechaPago, $ventanilla, $codi, $matricula, $digito, $nfac, $monto, $leyenda, $cuf, $cufd){
        $sqlImpuestos = sprintf(
            "UPDATE impuestos SET imp_nume = '%s', cuf = '%s', cufd = '%s', leyenda = '%s', num_fact = %d WHERE IMP_MATR = '%s' and IMP_DIVE = '%s' and imp_nfac = %d",
            $nroFacturaAntiguo,
            $cuf,
            $cufd,
            $leyenda,
            $nroFacturaAntiguo,
            $matricula,
            $digito,
            $nfac
        );

        $this->executeCrudSQL($sqlImpuestos);

        $sql = sprintf(
            "UPDATE PLANILLA SET pla_nmes = pla_nmes - 1, pla_totacu = pla_totacu - $monto  WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac > %d",
            $matricula,
            $digito,
            $nfac
        );
        $this->executeCrudSQL($sql);

        $sql = sprintf(
            "UPDATE PLANILLA SET PLA_NUME = '%s', PLA_TOTANT =  0, PLA_NMES = 1, PLA_TOTACU = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon), PLA_FCON = {d'%s'}, PLA_FPAG = {d'%s'}, PLA_INTCRE = %d, PLA_CODI = %d, PLA_VLPG = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon), pla_totmes = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon)  WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
            $nroFacturaAntiguo,
            $fechaPago,
            $fechaPago,
            $ventanilla,
            $codi,
            $matricula,
            $digito,
            $nfac
        );
        //return ($sql);
        return $this->executeCrudSQL($sql);
        /*$data = $this->executeSelectSQL($sql1);
        if (count($data)==0){
            return json_encode([]);
        }
        else{
            return json_encode($data);
        }*/
    }
    private function actualizarPlaAntAntiguo($matricula, $digito, $factura, $totacu)
    {
        $facturaSiguiente = $factura + 1;
        $sql = sprintf(
            "UPDATE PLANILLA SET pla_totant = 0 WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
            $matricula,
            $digito,
            $facturaSiguiente
        );
        $this->executeCrudSQL($sql);

        $sql = sprintf(
            "UPDATE PLANILLA SET pla_totant = pla_totant - $totacu WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac > %d",
            $matricula,
            $digito,
            $facturaSiguiente
        );
        return $this->executeCrudSQL($sql);
    }
    public function updateCorte($monto, $nroFactura, $fecha,$numero_factura,$cuenta,$nfac){
        $sql = sprintf(
            "UPDATE corte SET cor_monto = %f, num_fact = %d, cor_fpag = {ts'$fecha'}, cor_nren = '%s' WHERE cor_matr = '%s' and empty(cor_fpag) and !empty(cor_freal) and cor_nfac = %d ",
            $monto,
            $nroFactura,
            $numero_factura,
            $cuenta,
            $nfac
        );
        error_log($sql);
        $this->executeCrudSQL($sql);
        $ultimoCorte = $this->getUltimoCorte($cuenta);
        return json_encode(["success" => true, "message" => "Corte actualizado"]);
    }

    public function getUltimoCorte($cuenta){
        $sql = sprintf(
            "select top 1 * from corte where cor_matr = '%s' order by cor_nfac desc",
            $cuenta
        );
        $data = $this->executeSelectSQL($sql);
        return $data;
    }
    public function getValorTipoCorte($request)
    {
        try {
            $sql = sprintf("
            select tip_valor from tipocorte where tip_codi = %d
            ", $request['tip_codi']);
            $data = $this->executeSelectSQL($sql);
            if(count($data) != 0){
                return $data[0]->tip_valor;
            }else{
                return 'vacio';
            }
        } catch (\Throwable $e) {
            throw $e;
        }
    }
    public function cancelarFactura($cuenta, $nfacs, $facturasAntiguas, $ventanilla, $sucursal, $nroFactura, $cuf, $cufd, $leyenda, $cuis,$fecha,$cuotas,$glosa,$cuenta_selasis,$request)
    {
        try {
            $matricula = substr($cuenta, 0, -1);
            $digito = substr($cuenta, -1);
            $facturasComa = implode(",", $nfacs);
            $fechaPago = date("Y-m-d");
            $fecha_pago = date('Y-m-d');

            $fechaFin = date("Y-m-d H:i:s", strtotime($fecha_pago ));
            $fechaFin1 = strtotime('+23 hour', strtotime($fechaFin));
            $fechaFin2 = date("Y-m-d H:i:s", $fechaFin1);

            $fechaInicio = date("Y-m-d H:i:s", strtotime($fecha_pago));

            $numero_factura = str_pad($nroFactura, 10, "0", STR_PAD_LEFT);
            $fechaHora = date("Y-m-d H:i:s A");
            $fechaPago = date("Y-m-d");
            $matricula = substr($cuenta, 0, -1);
            $digito = substr($cuenta, -1);
            $facturasComa = implode(",", $nfacs);


            if (!empty($facturasAntiguas) && empty($nfacs)){
                $ultimoAntiguo = end($facturasAntiguas)['nfac'];
                $datosPagIngre = $this->getDatosPagIngre($matricula);


                $this->cancelarMulta($datosPagIngre[0], $cuenta, $nroFactura, $ventanilla, $cuf, $cufd, $leyenda, $cuis, $sucursal, $ultimoAntiguo,$fecha,$cuotas,$glosa,$cuenta_selasis,$request);
                $nfacsAntiguos = [];
                
                $nfacsAntiguos = $this->insertFacturasAntiguas($facturasAntiguas, $nfacsAntiguos, $fechaPago, $ventanilla, $matricula, $digito, $fechaHora, $sucursal, $cuf, $cufd, $leyenda, $cuis,$fecha);
                //return $nfacsAntiguos;
                $facturaAntiguoFinal = end($nfacsAntiguos);
                
                //return json_encode($facturaAntiguoFinal);

                $facturasComaAntiguas = implode(",", $nfacsAntiguos);
                $sql = $this->getSqlPlanilla() . sprintf("
                WHERE PLA_MATR = '$matricula' and PLA_nfac in ($facturasComaAntiguas)");
                
                $facturasAnt = $this->executeSelectSQL($sql);
                //return json_encode($facturasAnt);
                $cliente = $this->getCliente($facturasAnt[0], $matricula, $digito);

                $detallesOtroIngreso = [];
                $facturasCanceladas = [];
                
                foreach ($facturasAnt as $factura) {
                    $detalles = [];
                    $detalles = $this->getDetallesFactura($factura, $detalles);

//                    $this->addDetail($factura, $detalles, 0, 'Antiguo');

                    $facturasCanceladas [] = $this->addDetail($factura, $detalles, 0, 'Antiguo');

                }

                $facturaOtroIngreso = $this->getPagOtroIngre($fecha_pago, $fechaInicio, $fechaFin2, $nroFactura);

                if (count($facturaOtroIngreso) > 0 && $nroFactura > 0) {

                    $facturasCanceladas = $this->getFacturaOtroIngreso($matricula, $facturaOtroIngreso, $detallesOtroIngreso, $facturasCanceladas);
                }

                return json_encode(["success" => true, "message" => "Pago realizado Exitosamente", "cliente" => $cliente, "facturas" => $facturasCanceladas]);
            }

            if (!empty($nfacs) && empty($facturasAntiguas)) {
                $facturaInicia = $nfacs[0];
                $facturaFinal = end($nfacs);
                if ($this->tieneDeudaAnterior($matricula, $digito, $facturaInicia)) {
                    return json_encode(["success" => false, "message" => "No se puede realizar el pago, por que existen facturas pasadas no canceladas"]);
                }

                $planilla = $this->getPlanillaPorFacturaNoPagadas($matricula, $digito, $facturasComa);
                if (count($planilla) > 0) {
                    $datosPagIngre = $planilla[0];
                    $this->cancelarMulta($datosPagIngre, $cuenta, $nroFactura, $ventanilla, $cuf, $cufd, $leyenda, $cuis, $sucursal, $facturaFinal,$fecha,$cuotas,$glosa,$cuenta_selasis,$request);
                    $factura = $this->cancelacionFacturasNormales($planilla, $fechaPago, $ventanilla, $matricula, $digito, $sucursal, $facturasComa, $fechaHora);

                    $sql = $this->getSqlPlanilla() . sprintf("
                WHERE PLA_MATR = '$matricula' and PLA_nfac in ($facturasComa)");
                    $facturas = $this->executeSelectSQL($sql);

                    $cliente = $this->getCliente($facturas[0], $matricula, $digito);

                    $detallesOtroIngreso = [];
                    $facturasCanceladas = [];
                    foreach ($facturas as $factura) {
                        $detalles = [];
                        $detalles = $this->getDetallesFactura($factura, $detalles);

                        $pagSer = $this->getCorrelativoCajero($factura);

                        //$this->addDetail($factura, $detalles, $pagSer);

                        $facturasCanceladas [] = $this->addDetail($factura, $detalles, $pagSer);

                    }

                    $facturaOtroIngreso = $this->getPagOtroIngre($fecha_pago, $fechaInicio, $fechaFin2, $nroFactura);

                    if (count($facturaOtroIngreso) > 0 && $nroFactura > 0) {

                        $facturasCanceladas = $this->getFacturaOtroIngreso($matricula, $facturaOtroIngreso, $detallesOtroIngreso, $facturasCanceladas);
                    }

                    return json_encode(["success" => true, "message" => "Pago realizado Exitosamente", "cliente" => $cliente, "facturas" => $facturasCanceladas]);
                } else {
                    return json_encode(['success' => false, 'message' => 'La factura ya ha sido pagada con anterioridad']);
                }
            }




            if (!empty($nfacs) && !empty($facturasAntiguas)) {
                $facturaInicia = $nfacs[0];
                $facturaFinal = end($nfacs);
                $facturaIniciaAntiguo = $facturasAntiguas[0]['nfac'];

                if ($this->tieneDeudaAnterior($matricula, $digito, $facturaIniciaAntiguo)) {
                    return json_encode(["success" => false, "message" => "No se puede realizar el pago, por que existen facturas pasadas no canceladas"]);
                }

                $planilla = $this->getPlanillaPorFacturaNoPagadas($matricula, $digito, $facturasComa);

                if (count($planilla) > 0) {
                    $datosPagIngre = $planilla[0];

                    //cancelacion de la multa factura de otros ingresos
                    $this->cancelarMulta($datosPagIngre, $cuenta, $nroFactura, $ventanilla, $cuf, $cufd, $leyenda, $cuis, $sucursal, $facturaFinal,$fecha,$cuotas,$glosa,$cuenta_selasis,$request);

                    //cancelacion facturas antiguas
                    $nfacsAntiguos = [];
                    $nfacsAntiguos = $this->insertFacturasAntiguas($facturasAntiguas, $nfacsAntiguos, $fechaPago, $ventanilla, $matricula, $digito, $fechaHora, $sucursal, $cuf, $cufd, $leyenda, $cuis,$fecha);
                    //return json_encode($nfacsAntiguos);
                    //cancelacion facturas normales
                    $factura = $this->cancelacionFacturasNormales($planilla, $fechaPago, $ventanilla, $matricula, $digito, $sucursal, $facturasComa, $fechaHora);

                    //datos de facturas antiguas para la impresio
                    $facturasComaAntiguas = implode(",", $nfacsAntiguos);

                    $sql = $this->getSqlPlanilla() . sprintf("
                WHERE PLA_MATR = '$matricula' and PLA_nfac in ($facturasComaAntiguas)");
                    $facturasAnt = $this->executeSelectSQL($sql);

                    $cliente = $this->getCliente($facturasAnt[0], $matricula, $digito);

                    $facturasCanceladas = [];
                    foreach ($facturasAnt as $factura) {
                        $detalles = [];
                        $detalles = $this->getDetallesFactura($factura, $detalles);

                        $this->addDetail($factura, $detalles, 0);

                        $facturasCanceladas [] = $this->addDetail($factura, $detalles, 0, 'Antiguo');

                    }


                    //datos facturas normales para la impresion
                    $sql = $this->getSqlPlanilla() . sprintf("
                WHERE PLA_MATR = '$matricula' and PLA_nfac in ($facturasComa)");
                    $facturas = $this->executeSelectSQL($sql);

                    $cliente = $this->getCliente($facturas[0], $matricula, $digito);

                    $detallesOtroIngreso = [];
                    //$facturasCanceladas = [];
                    foreach ($facturas as $factura) {
                        $detalles = [];
                        $detalles = $this->getDetallesFactura($factura, $detalles);

                        $pagSer = $this->getCorrelativoCajero($factura);

                        $this->addDetail($factura, $detalles, $pagSer);

                        $facturasCanceladas [] = $this->addDetail($factura, $detalles, $pagSer);

                    }

                    //datos facturas de otros ingresos para impresion
                    $facturaOtroIngreso = $this->getPagOtroIngre($fecha_pago, $fechaInicio, $fechaFin2, $nroFactura);

                    if (count($facturaOtroIngreso) > 0 && $nroFactura > 0) {

                        $facturasCanceladas = $this->getFacturaOtroIngreso($matricula, $facturaOtroIngreso, $detallesOtroIngreso, $facturasCanceladas);
                    }

                    return json_encode(["success" => true, "message" => "Pago realizado Exitosamente", "cliente" => $cliente, "facturas" => $facturasCanceladas]);
                } else {
                    return json_encode(['success' => false, 'message' => 'La factura ya ha sido pagada con anterioridad']);
                }
            }

//            else{
//                $facturaFinal = end($nfacs);
//                $sqldatosPagIngre = sprintf("select usr_apno, usr_dirr, usr_rucnit,usr_circ,
//                                                    usr_cdig,
//                                                    usr_cdi1,
//                                                    usr_numm,
//                                                    usr_cicl,
//                                                    usr_sect,
//                                                    usr_ruta,
//                                                    usr_manz,
//                                                    usr_rloc,
//                                                    tac_desc,
//                                                    cat_desc
//                                            from puntcons
//                                            join categori on puntcons.usr_cate = categori.usr_cate
//                                             join tipactiv on puntcons.usr_tiac = tipactiv.usr_tact
//                                            where usr_matr = '%s'", $matricula);
//                $datosPagIngre = $this->executeSelectSQL($sqldatosPagIngre);
//                $this->cancelarMulta($datosPagIngre[0], $cuenta, $nroFactura, $ventanilla, $cuf, $cufd, $leyenda, $cuis, $sucursal, $facturaFinal);
//
//                $cliente = $this->getClienteSoloOtroIngre($datosPagIngre[0], $matricula, $digito, $ventanilla);
//
//                $detallesOtroIngreso = [];
//                $facturasCanceladas = [];
//                $facturaOtroIngreso = $this->getPagOtroIngre($fecha_pago, $fechaInicio, $fechaFin, $nroFactura);
//
//                if (count($facturaOtroIngreso) > 0 && $nroFactura > 0) {
//
//                    $facturasCanceladas = $this->getFacturaOtroIngreso($facturaOtroIngreso, $detallesOtroIngreso, $facturasCanceladas);
//                }
//                return json_encode(["success" => true, "message" => "Pago realizado Exitosamente", "cliente" => $cliente, "facturas" => $facturasCanceladas]);
//
//            }
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    private function tieneDeudaAnterior($matricula, $digito, $factura)
    {
        $sql = "select * from planilla where PLA_MATR = '$matricula' and PLA_DIVE = '$digito' and PLA_NFAC < $factura and empty(PLA_FPAG) and pla_codi != 8";
        $dataDeudaAnterior = $this->executeSelectSQL($sql);
        // if(!$dataDeudaAnterior) $this->pararEjecucion();
        return count($dataDeudaAnterior) > 0;
    }

    private function getPlanillaPorFacturaNoPagadas($matricula, $digito, $facturas)
    {
        $sql = sprintf(
            "select PLA_TOTMES,pla_corte, PLA_CODI, PLA_VLPG, PLA_FPAG, PLA_NUME, PLA_REDON, PLA_CCON, pla_nfac,
                        pla_vlag, pla_vlma, pla_form, pla_leyprv, pla_abono, pla_inag, pla_cuocre, pla_totacu,
                        usr_apno, usr_dirr, usr_rucnit
                        from planilla
                        join puntcons on planilla.pla_matr = puntcons.usr_matr
                        WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(pla_fpag) and pla_nfac in ($facturas)
                        order by pla_nfac asc",
            $matricula,
            $digito
        );
        return $this->executeSelectSQL($sql);
    }

    private function getPlanillaPorFactura($matricula, $digito, $facturas)
    {
        $sql = sprintf(
            "select PLA_TOTMES, PLA_CODI, PLA_VLPG, PLA_FPAG, PLA_NUME, PLA_REDON, PLA_CCON, pla_nfac
                        from planilla
                        WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac in ($facturas)",
            $matricula,
            $digito
        );
        $planilla = $this->executeSelectSQL($sql);

        if (count($planilla) > 0)
            return $planilla;
        else
            return null;
    }

    private function actualizarPlanilla($fechaHora, $fechaPago, $ventanilla, $codi, $matricula, $digito, $factura, $totalMes)
    {
//        $fechaHora = date("Y-m-d H:i:s A");
//        $sql = sprintf(
//            "UPDATE impuestos SET imp_fpag = {ts'%s'} WHERE IMP_MATR = '%s' and IMP_DIVE = '%s' and imp_nfac = %d",
//            $fechaHora,
//            $matricula,
//            $digito,
//            $factura
//        );
//        $this->executeCrudSQL($sql);

        $sql = sprintf(
            "UPDATE PLANILLA SET pla_nmes = pla_nmes - 1, pla_totacu = pla_totacu - $totalMes  WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac > %d and pla_codi != 8",
            $matricula,
            $digito,
            $factura
        );
        $this->executeCrudSQL($sql);

        //"UPDATE PLANILLA SET PLA_FPAG = {d'%s'}, PLA_INTCRE = %d, PLA_CODI = %d, PLA_VLPG = %f, PLA_HORA = {ts'$fechaHora'}   WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
        $sql = sprintf(
            "UPDATE PLANILLA SET PLA_TOTANT =  0, PLA_NMES = 1, PLA_TOTACU = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon), PLA_FCON = {d'%s'}, PLA_FPAG = {d'%s'}, PLA_INTCRE = %d, PLA_CODI = %d, PLA_VLPG = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon), pla_totmes = (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon)  WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d and pla_codi != 8",
            $fechaPago,
            $fechaPago,
            $ventanilla,
            $codi,
            $matricula,
            $digito,
            $factura
        );
        return $this->executeCrudSQL($sql);

    }

    private function actualizarPlaAnt($matricula, $digito, $factura, $totacu)
    {
        $facturaSiguiente = $factura + 1;
        $sql = sprintf(
            "UPDATE PLANILLA SET pla_totant = 0 WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d and pla_codi != 8",
            $matricula,
            $digito,
            $facturaSiguiente
        );
        $this->executeCrudSQL($sql);

        $sql = sprintf(
            "UPDATE PLANILLA SET pla_totant = pla_totant - $totacu WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac > %d and pla_codi != 8",
            $matricula,
            $digito,
            $facturaSiguiente
        );
        return $this->executeCrudSQL($sql);
    }

    private function insertarPagos($matricula, $ventanilla, $digito, $factura, $totalMes, $fechaPago, $nrem, $ccon, $controlInterno, $sucu)
    {
        $sqlInsert = "insert into pagos (PAG_MATR, PAG_BANC, PAG_DIVE, PAG_NFAC, PAG_VLPG, PAG_FPAG, PAG_FCIE, PAG_FSYS, PAG_NREN, PAG_ESTA, PAG_CCON, PAG_SER, PAG_CODI, PAG_SUCU) values('$matricula', $ventanilla, '$digito', $factura, $totalMes, {d'$fechaPago'}, {},{},'$nrem','C','$ccon',$controlInterno,0,'$sucu')";
        return $this->executeCrudSQL($sqlInsert);

    }

    public function anularTablaFicha($factura, $fechaInicio, $fechaFin)
    {
        $sqlRestoreFicha = sprintf(
            "update ficha set num_fact = null, fic_flag = .F. where fic_fpre > {ts'%s'} and fic_fpre < {ts'%s'} and num_fact = %d",
            $fechaInicio,
            $fechaFin,
            $factura
        );
        return $this->executeCrudSQL($sqlRestoreFicha);
    }

    public function anularTablaCorte($factura, $fechaInicio, $fechaFin)
    {
        $sqlRestoreCorte = sprintf(
            "UPDATE corte SET cor_fpag = {}, num_fact = null WHERE cor_fpag > {ts'%s'} and cor_fpag < {ts'%s'} and num_fact = %d ",
            $fechaInicio,
            $fechaFin,
            $factura
        );
        return $this->executeCrudSQL($sqlRestoreCorte);
    }

    public function anularPlaCorte($factura, $fecha)
    {
        $fechaPago = date("Y-m-d", strtotime($fecha));
        $sqlOtroIngreso = sprintf(
            "select otr_matr, otr_dive, otr_nue, otr_vlpg from otroingre where otr_nue = 2213 and num_fact = %d and otr_esta = 'C'",
            $factura
        );
        $otroIngreso = $this->executeSelectSQL($sqlOtroIngreso);

        if(count($otroIngreso) > 0){
            $corte = $otroIngreso[0]->otr_vlpg;
            $matricula = $otroIngreso[0]->otr_matr;

            $sqlPlanilla = sprintf(
                "select pla_matr, pla_nfac from planilla where pla_matr = '%s' order by pla_nfac desc",
                $matricula
            );
            $planilla = $this->executeSelectSQL($sqlPlanilla);


            $sqlPlaCorteLimpieza = sprintf(
                "UPDATE PLANILLA SET pla_corte = %f WHERE PLA_MATR = '%s' and (pla_fpag = {d'$fechaPago'} or pla_fcon = {d'$fechaPago'})",
                0.00,
                $matricula
            );
            $planillaCorte = $this->executeSelectSQL($sqlPlaCorteLimpieza);

            $nfac = $planilla[0]->pla_nfac;


            $sqlMulta = sprintf(
                "UPDATE PLANILLA SET pla_corte = %f WHERE PLA_MATR = '%s' and pla_nfac = %d",
                $corte,
                $matricula,
                $nfac
            );

            return $this->executeCrudSQL($sqlMulta);
        }
        else
            return true;
    }
    public function anularFactura($cuenta, $factura, $codigo)
    {
        try {
            $matricula = substr($cuenta, 0, -1);
            $dive = substr($cuenta, -1);
            $fecha = date("Y-m-d");
            $hora = date('H:i:s');


            $sql = "select PLA_FPAG, PLA_NFAC from planilla where PLA_MATR = '$matricula' and PLA_DIVE = '$dive' and PLA_NFAC > $factura and PLA_FPAG = {d'$fecha'}";
            $data = $this->executeSelectSQL($sql);
            if (count($data) > 0) {
                return json_encode(["success" => false, "message" => "No se puede anular por que existe facuras posteriores canceladas"]);
            } else {
                $sql = sprintf(
                    "select PLA_TOTMES, PLA_CODI, PLA_VLPG, PLA_FPAG, PLA_NUME, PLA_REDON
                            from planilla
                            WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = %d",
                    $matricula,
                    $dive,
                    $factura
                );
                $data = $this->executeSelectSQL($sql);
                if (count($data) > 0) {
                    return json_encode(['success' => false, 'message' => 'Anule la factura por selasis']);
                    if (empty($data[0]->pla_fpag))
                        return json_encode(['success' => false, 'message' => 'La factura ya fue anulada']);

                    $updatePagos = "UPDATE PAGOS SET PAG_ESTA = 'A', PAG_CODI = $codigo WHERE PAG_MATR = '$matricula' and PAG_DIVE = '$dive' and PAG_NFAC = $factura";
                    $this->executeCrudSQL($updatePagos);

                    $ventanilla = 0;
                    $totalMes = 0.00;
                    $codi = 3;
                    if($data[0]->codi == 4)
                    {
                        $codi = 4;
                    }
                    $nume = $data[0]->pla_nume;
                    //$updatePlanilla = sprintf("UPDATE PLANILLA SET PLA_FPAG = {}, PLA_INTCRE = %d, PLA_CODI = %d, PLA_VLPG = %f, pla_hora = {}, fecha_qr = {}
                    //                        WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = $factura",
                    $updatePlanilla = sprintf("UPDATE PLANILLA SET PLA_FPAG = {}, PLA_INTCRE = %d, PLA_CODI = %d, PLA_VLPG = %f
                        WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = $factura",
                        $ventanilla,
                        $codi,
                        $totalMes,
                        $matricula,
                        $dive,
                        $factura);
                    $this->executeCrudSQL($updatePlanilla);
                    return json_encode(['success' => true]);
                } else {
                    return json_encode(['success' => false, 'message' => 'La factura no existe o ya fue anulada']);
                }
            }
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
    public function anularCuotaSancion($request){
        $san_nros=$request['san_nros'];
        $san_cuota=$request['san_cuota'];
        $san_matr=$request['san_matr'];
        $sql = sprintf(
            "UPDATE sanciones SET san_cuota = san_cuota - %d, san_pcon=san_pcon-1 WHERE san_nros = %d and san_matr = '%s'",
            $san_cuota,
            $san_nros,
            $san_matr
        );
        error_log($sql);
        $this->executeCrudSQL($sql);
        return json_encode(["success" => true, "message" => "Cuota anulada"]);
    }
    public function anularOtroIngreso($factura, $fecha, $tipo)
    {
        try {
            $fechaFin = date("Y-m-d H:i:s", strtotime($fecha ));
            $fechaFin1 = strtotime('+23 hour', strtotime($fechaFin));
            $fechaFin2 = date("Y-m-d H:i:s", $fechaFin1);

            $fechaInicio = date("Y-m-d H:i:s", strtotime($fecha));

            $this->anularTablaFicha($factura, $fechaInicio, $fechaFin2);
            $this->anularTablaCorte($factura, $fechaInicio, $fechaFin2);
            $this->anularPlaCorte($factura, $fecha);

            $sql = sprintf(
                "select 
                    pagingre.num_fact,
                    pag_ctra,
                    pag_nren
        from pagingre
        join otroingre on pagingre.pag_nren = otroingre.otr_nren
        WHERE pag_fpag between {ts'%s'} and {ts'%s'} and otr_fpag > {ts'%s'} and otr_fpag < {ts'%s'} and pagingre.num_fact = %d 
        ", $fechaInicio, $fechaFin2, $fechaInicio, $fechaFin2, $factura);

            $data = $this->executeSelectSQL($sql);
            //return json_encode(["data" => $data]);
            if (count($data) > 0) {

                $updatePagIngreSql = sprintf("UPDATE pagingre SET PAG_ESTA = 'A' WHERE pag_fpag between {ts'%s'} and {ts'%s'} and num_fact = %d",
                    $fechaInicio,
                    $fechaFin2,
                    $factura);
                $updatePagIngre = $this->executeCrudSQL($updatePagIngreSql);
                if($updatePagIngre === false)
                    $this->restoreOtroIngresoAnulacion($factura, $fecha);

                $updateOtroIngreSql = sprintf("UPDATE otroingre SET otr_esta = 'A'
                        WHERE otr_fpag > {ts'%s'} and otr_fpag < {ts'%s'} and num_fact = %d",
                    $fechaInicio,
                    $fechaFin2,
                    $factura,
                );
                $updateOtroIngre = $this->executeCrudSQL($updateOtroIngreSql);
                if($updateOtroIngre === false)
                    $this->restoreOtroIngresoAnulacion($factura, $fecha);
                $message = $tipo;
                //restaurar venta agua otros ingresos
                if($tipo=='Cisterna'){
                    $cisterna= new CisternaController();
                    $message=$cisterna->restaurarVentaAgua($data[0]->pag_ctra,$fechaInicio,$fechaFin2);
                }
                if($tipo=='Credito'){
                    $credito= new CreditoController();
                    $message=$credito->restaurarCredito($data[0]->pag_ctra,$data[0]->pag_nren,$fechaInicio,$fechaFin);
                }
                if($tipo=='OtroServicio'){
                    $credito= new CreditoController();
                    if ($data[0]->pag_ctra!=''){
                        $message=$credito->restaurarCredito($data[0]->pag_ctra,$data[0]->pag_nren,$fechaInicio,$fechaFin);
                    }
                }
                if($tipo=='Tramite'){
                    $tramite = new TramitesController();
                    $message = $tramite->restoreFichaCancelacion($factura, $fechaInicio, $fechaFin2);
                }

                return json_encode(['success' => true, 'message' => $message]);
            } else {
                return json_encode(['success' => false, 'message' => 'La factura no existe o ya fue anulada']);
            }

        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function duplicarFactura($cuenta, $factura, $codigo)
    {
        try {
            $matricula = substr($cuenta, 0, -1);
            $dive = substr($cuenta, -1);


            $sql = $this->getSqlPlanilla() . sprintf(" WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = %d", $matricula, $dive, $factura);
            $factura = $this->executeSelectSQL($sql);

            $cliente = $this->getCliente($factura[0], $matricula, $dive);

            if (empty($factura[0]->pla_fpag) || count($factura) == 0) {
                return json_encode(['success' => false, 'message' => 'No se puede duplicar la factura por que no esta cancelada o no existe']);
            } else {
                $detalles = [];
                $detalles = $this->getDetallesFactura($factura[0], $detalles);

                $pagSer = $this->getCorrelativoCajero($factura[0]);
                $facturasCancelada = $this->addDetail($factura[0], $detalles, $pagSer);

                return json_encode(['success' => true, "cliente" => $cliente, "factura" => $facturasCancelada]);
            }

        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }
    public function duplicarOtroIngreso($factura, $cuf, $fecha, $codigo, $opcion_otro_ingreso)
    {
        $fechaFin = date("Y-m-d H:i:s", strtotime($fecha ));
        $fechaFin1 = strtotime('+24 hour', strtotime($fechaFin));
        $fechaFin2 = date("Y-m-d H:i:s", $fechaFin1);

        $fechaInicio = date("Y-m-d H:i:s", strtotime($fecha));

        $facturaOtroIngreso = $this->getPagOtroIngre($fecha, $fechaInicio, $fechaFin2, $factura);
        //return json_encode(["lol" => $facturaOtroIngreso]);
        $medidor = "";
        if($opcion_otro_ingreso=='Cisterna'){
            $ven_regi = $facturaOtroIngreso[0]->pag_ctra;
            $sql = sprintf("SELECT * FROM ventagua WHERE ven_regi = %d", $ven_regi);
            $data = $this->executeSelectSQL($sql);
            $medidor = $data[0]->ven_placa;
        }
        $cliente = [
            "razon_social" => $facturaOtroIngreso[0]->pag_apno,
            "direccion" => $facturaOtroIngreso[0]->pag_direc,
            "cuenta" => $facturaOtroIngreso[0]->pag_matr.$facturaOtroIngreso[0]->pag_dive,
            "ci" => $facturaOtroIngreso[0]->pag_rucnit,
            "medidor" => $medidor,
        ];

        if(!empty($facturaOtroIngreso[0]->pag_matr)){
            $datosPagIngre = $this->getDatosPagIngre($facturaOtroIngreso[0]->pag_matr);

            $cliente = [
                "actividad" => $datosPagIngre[0]->tac_desc,
                "circuito" => $datosPagIngre[0]->usr_circ . $datosPagIngre[0]->usr_cdig . $datosPagIngre[0]->usr_cdi1,
                "catastro" => $datosPagIngre[0]->usr_cicl . $datosPagIngre[0]->usr_sect . $datosPagIngre[0]->usr_ruta . $datosPagIngre[0]->usr_manz . $datosPagIngre[0]->usr_rloc,
                "categoria" => $datosPagIngre[0]->cat_desc,
                "medidor" => $medidor==''? $datosPagIngre[0]->usr_numm : $medidor,
                "razon_social" => $facturaOtroIngreso[0]->pag_apno,
                "direccion" => $facturaOtroIngreso[0]->pag_direc,
                "cuenta" => $facturaOtroIngreso[0]->pag_matr.$facturaOtroIngreso[0]->pag_dive,
                "ci" => $facturaOtroIngreso[0]->pag_rucnit
            ];
        }

        $cabecera = [
            "cuf" => $facturaOtroIngreso[0]->cuf,
            "fecha" => $facturaOtroIngreso[0]->pag_fpag1,
            "glosa" => $facturaOtroIngreso[0]->pag_glos,
            "leyenda" => $facturaOtroIngreso[0]->leyenda,
            "nroFactura" => $facturaOtroIngreso[0]->num_fact,
            "total" => $facturaOtroIngreso[0]->pag_valo,
            "ventanilla" => $facturaOtroIngreso[0]->pag_banc,
            "estado" => $facturaOtroIngreso[0]->pag_esta
        ];
        $cuentas= new CuentasController();
        foreach ($facturaOtroIngreso as $detalleOtro) {
            $cantidad=1;

            if (strpos($detalleOtro->pag_glos, trim($cuentas->searchCuenta(11153)->nombre)) !== false && $detalleOtro->otr_nue == '11153') {
                $data = $this->executeSelectSQL(sprintf("SELECT * FROM ventagua WHERE ven_regi = %d ", $codigo));
                $cantidad = $data[0]->ven_metr;
            }
            $detallesOtroIngreso [] = [
                "codigoProducto" => $detalleOtro->otr_nue,
                "descripcion" => $detalleOtro->otr_conc,
                "precioUnitario" => $detalleOtro->otr_vlpg,
                "cantidad" => intval($cantidad),
                "tarifa" => null
            ];
        }

        $detallesFactura  = $detallesOtroIngreso;

        $facturasCanceladas  = [
            "cabecera" => $cabecera,
            "cliente" => $cliente,
            "detalles" => $detallesFactura
        ];

        return json_encode(["success" => true, "message" => "Factura duplicada exitosamente", "factura" => $facturasCanceladas]);
    }

    private function getSqlPlanilla()
    {
//        $sql = "select impuestos.num_fact, puntcons.USR_MATR, puntcons.USR_DIVE, puntcons.USR_CICL, puntcons.USR_SECT, puntcons.USR_RUTA, puntcons.USR_MANZ, puntcons.USR_RLOC, puntcons.USR_RUCNIT,
//                puntcons.USR_NUMM, puntcons.USR_CIRC, puntcons.USR_CDIG, puntcons.USR_CDI1, PLA_FEMI, PLA_FVEN, PLA_LEC1,
//                PLA_LEC2, PLA_CNS1, PLA_VLAG, PLA_VLMA, PLA_FORM, PLA_LEYPRV, PLA_CODABO, PLA_ABONO, PLA_NMES, PLA_TOTANT,
//                PLA_INAG, PLA_CUOCRE, PLA_INTCRE, PLA_TOTMES, PLA_TOTACU, PLA_CODI, PLA_VLPG, PLA_FPAG, PLA_NUME, PLA_REDON,
//                PLA_CCON, PLA_NFAC, PLA_TARI, PLA_FLEC1, PLA_FLEC2, pla_cate, pla_cod1, pla_codabo, puntcons.usr_tiac, puntcons.usr_apno, puntcons.usr_dirr, impuestos.cuf, impuestos.leyenda,
//                cat_desc, con_desc, tac_desc, impuestos.es_conci, impuestos.mon_conci, pla_rucnit
//        from PLANILLA
//                join impuestos on planilla.pla_matr = impuestos.imp_matr and planilla.pla_nfac = impuestos.imp_nfac
//                join categori on planilla.pla_cate = categori.usr_cate
//                join puntcons on planilla.pla_matr = puntcons.usr_matr
//                join tipactiv on puntcons.usr_tiac = tipactiv.usr_tact
//                join concepto on planilla.pla_codabo = concepto.cfc_codi
//                join consumo on planilla.pla_cod1 = consumo.con_codi
//        ";

        $sql = "select  planilla.pla_nume as num_fact, puntcons.USR_MATR, puntcons.USR_DIVE, puntcons.USR_CICL, puntcons.USR_SECT, puntcons.USR_RUTA, puntcons.USR_MANZ, puntcons.USR_RLOC, puntcons.USR_RUCNIT,
                puntcons.USR_NUMM, puntcons.USR_CIRC, puntcons.USR_CDIG, puntcons.USR_CDI1, PLA_FEMI, PLA_FVEN, PLA_LEC1,
                PLA_LEC2, PLA_CNS1, PLA_VLAG, PLA_VLMA, PLA_FORM, PLA_LEYPRV, PLA_CODABO, PLA_ABONO, PLA_NMES, PLA_TOTANT,
                PLA_INAG, PLA_CUOCRE, PLA_INTCRE, PLA_TOTMES, PLA_TOTACU, PLA_CODI, PLA_VLPG, PLA_FPAG, PLA_NUME, PLA_REDON,
                PLA_CCON, PLA_NFAC, PLA_TARI, PLA_FLEC1, PLA_FLEC2, pla_cate, pla_cod1, pla_codabo, puntcons.usr_tiac, puntcons.usr_apno, puntcons.usr_dirr,
                cat_desc, con_desc, tac_desc, pla_rucnit,'' as cuf, '' as es_conci,'' as mon_conci, pla_nan1
        from PLANILLA
                join categori on planilla.pla_cate = categori.usr_cate
                join puntcons on planilla.pla_matr = puntcons.usr_matr
                join tipactiv on puntcons.usr_tiac = tipactiv.usr_tact
                join concepto on planilla.pla_codabo = concepto.cfc_codi
                join consumo on planilla.pla_cod1 = consumo.con_codi
        ";

        return $sql;
    }

    public function getReporteArqueo($ventanilla, $fecha)
    {
        try {
            $fechaDesde = $fecha . " 00:00:00";
            $fechaHasta = $fecha . " 23:59:59";


            //$sqlPagosOtroIngreNoDev = "select sum(pag_valo), count(num_fact) from pagingre where pag_fpag between {ts'$fechaDesde'} and {ts'$fechaHasta'} and pag_esta = 'D' and pag_banc = $ventanilla";
            //$arqueoOtroIngreNodev = $this->executeSelectSQL($sqlPagosOtroIngreNoDev);

            $sqlPagos = "select sum(pag_vlpg), count(pag_nfac) from pagos where pag_fpag = {d'$fecha'} and pag_esta = 'C' and pag_banc = $ventanilla";
            $arqueo = $this->executeSelectSQL($sqlPagos);

            $sqlAnulados = "select count(pag_nfac) from pagos where pag_fpag = {d'$fecha'} and pag_esta = 'A' and pag_banc = $ventanilla";
            $anulados = $this->executeSelectSQL($sqlAnulados);

            $sqlPagosOtroIngre = "select sum(pag_valo), count(num_fact) from pagingre where pag_fpag between {ts'$fechaDesde'} and {ts'$fechaHasta'} and (pag_esta = 'C') and pag_banc = $ventanilla";
            $arqueoOtroIngre = $this->executeSelectSQL($sqlPagosOtroIngre);

            $sqlAnuladosOtroIngre = "select count(num_fact) from pagingre where pag_fpag between {ts'$fechaDesde'} and {ts'$fechaHasta'} and pag_esta = 'A' and pag_banc = $ventanilla";
            $anuladosOtroIngre = $this->executeSelectSQL($sqlAnuladosOtroIngre);

            $montoConsumo = count($arqueo) <= 0 ? 0 : $arqueo[0]->sum_pag_vlpg;
            $anuladosConsumo = count($anulados) <= 0 ? 0 : $anulados[0]->cnt_pag_nfac;

            $montoOtroingre = count($arqueoOtroIngre)<=0?0:$arqueoOtroIngre[0]->sum_pag_valo;
            $anuladosOtroIngre = count($anuladosOtroIngre)<=0?0:$anuladosOtroIngre[0]->cnt_num_fact;


            $arqueoTotal = $montoConsumo + $montoOtroingre;
            $arqueoTotal = round($arqueoTotal, 2);
            $literal = NumeroALetras::convert($arqueoTotal, 'bolivianos', false);

            $cantidadAnuladas = $anuladosConsumo + $anuladosOtroIngre;
            $cantidadFacturas = (count($arqueo) <= 0 ? 0 : $arqueo[0]->cnt_pag_nfac)+(count($arqueoOtroIngre)<=0?0:$arqueoOtroIngre[0]->cnt_num_fact);

            return json_encode([
                "success" => true,
                "totalConsumoAgua" => $montoConsumo,
                "cantidadFacturasConsumo"=> (count($arqueo) <= 0 ? 0 : $arqueo[0]->cnt_pag_nfac),
                "cantidadFacturasOtrosingresos"=>(count($arqueoOtroIngre)<=0?0:$arqueoOtroIngre[0]->cnt_num_fact),
                "anuladosConsumo" => $anuladosConsumo,
                "totalOtroIngreso" => $montoOtroingre,
                "anuladosOtroIngre" => $anuladosOtroIngre,
                "arqueoTotal" => $arqueoTotal,
                "literal" => $literal,
                "cantidadFacturas" => $cantidadFacturas,
                "cantidadAnuladas" => $cantidadAnuladas
            ]);
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function imprimirFactura($matricula, $digito, $factura)
    {
        try {
            $sqlAbril2022 = sprintf("
            select pla_tari
            from planilla
            WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = %d", $matricula, $digito, $factura);
            $esAbril2022 = $this->executeSelectSQL($sqlAbril2022);
            $fechaHora = date("Y-m-d H:i:s A");

            $sql = $this->getSqlPlanilla() . sprintf("
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PLA_NFAC = %d", $matricula, $digito, $factura);


            $facturaCancelada = $this->executeSelectSQL($sql);

            $matr = $facturaCancelada[0]->usr_matr;
            $puntconsSql = "select CAST(CONVERT(usr_apno USING utf8) AS binary) as nombre, CAST(CONVERT(usr_dirr USING utf8) AS binary) as direccion from puntcons where usr_matr = '$matr'";
            $puntcons = MySQLController::convertToObject(MySQLController::executeQuery($puntconsSql));

            $nombreCompleto = $puntcons[0]->nombre;
            $direccion = $puntcons[0]->direccion;

            $fechaIni = $facturaCancelada[0]->pla_femi;
            $fechaFin = $facturaCancelada[0]->pla_femi;
            $cat = $facturaCancelada[0]->pla_cate;

            $cons = $facturaCancelada[0]->pla_cod1;
            $conce = $facturaCancelada[0]->pla_codabo;
            $tipa = $facturaCancelada[0]->usr_tiac;


            if (intval($esAbril2022[0]->pla_tari) >= 202204) {
                $rentahisSql = "select STR_TO_DATE(ren_flim, '%m/%d/%Y') as ren_flim, ren_orden, STR_TO_DATE(ren_feini, '%m/%d/%Y') as ren_feini, STR_TO_DATE(ren_fefin, '%m/%d/%Y') as ren_fefin, ren_art from rentahis1 where STR_TO_DATE(ren_feini, '%m/%d/%Y') <= '$fechaIni' and STR_TO_DATE(ren_fefin, '%m/%d/%Y') >= '$fechaFin'";
                $renta = MySQLController::convertToObject(MySQLController::executeQuery($rentahisSql));
            } else {
                $rentahis1Sql = "select STR_TO_DATE(ren_flim, '%m/%d/%Y') as ren_flim, ren_orden, STR_TO_DATE(ren_feini, '%m/%d/%Y') as ren_feini, STR_TO_DATE(ren_fefin, '%m/%d/%Y') as ren_fefin, ren_art from rentahis where STR_TO_DATE(ren_feini, '%m/%d/%Y') <= '$fechaIni' and STR_TO_DATE(ren_fefin, '%m/%d/%Y') >= '$fechaFin'";
                $renta = MySQLController::convertToObject(MySQLController::executeQuery($rentahis1Sql));
            }

            $pagSerSql = "select pag_ser, fecha_hora from numedia where matricula = '$matricula' and digito = '$digito' and nfac = '$factura' order by fecha_hora desc";
            $pagser = MySQLController::convertToObject(MySQLController::executeQuery($pagSerSql));

            $categoriSql = "select cat_desc, cat_desc_nuevo from categori where usr_cate = '$cat'";
            $categoria = MySQLController::convertToObject(MySQLController::executeQuery($categoriSql));

            $consumoSql = "select con_desc from consumo where con_codi = '$cons'";
            $consumo = MySQLController::convertToObject(MySQLController::executeQuery($consumoSql));

            $conceptoSql = "select cfc_desc from concepto where cfc_codi = '$conce'";
            $concepto = MySQLController::convertToObject(MySQLController::executeQuery($conceptoSql));

            $tipactivSql = "select tac_desc from tipactiv where usr_tact = '$tipa'";
            $tipactiv = MySQLController::convertToObject(MySQLController::executeQuery($tipactivSql));

            $cate = $categoria[0]->cat_desc;
            if(intval($esAbril2022[0]->pla_tari) >= 202207)
            {
                $cate = $categoria[0]->cat_desc_nuevo;
            }

            $monto = $facturaCancelada[0]->pla_vlpg;
            $orden = $renta[0]->ren_orden;
            $leyenda = $renta[0]->ren_art;
            $flim = $renta[0]->ren_flim;
            $pag = $pagser[0]->pag_ser;

            $fechaHoraFactura = $pagser[0]->fecha_hora;

            $con_desc = $consumo[0]->con_desc;
            $cfc_desc = $concepto[0]->cfc_desc;
            $tac_desc = $tipactiv[0]->tac_desc;

            $nume = $facturaCancelada[0]->pla_nume;
            $nume = preg_replace("/^0*/", "", $nume);

            $ruc = trim($facturaCancelada[0]->usr_rucnit);

            if (strlen($ruc) > 0) {
                $rucnit = $ruc;
            } else {
                $rucnit = 0;
            }

            $literal = strtoupper(NumeroALetras::convert($monto, 'bolivianos', false));

            $sqlPago = sprintf(
                "select * from pagos WHERE PAG_MATR = '%s' and PAG_DIVE = '%s' and PAG_NFAC = %d",
                $matricula,
                $digito,
                $factura
            );
            $pago = $this->executeSelectSQL($sqlPago);
            $control = $pago[0]->pag_ser;


            //$mesNumero = substr($facturaCancelada[0]->pla_tari, -2);
            $mesLiteral = $this->mesLiteral(substr($facturaCancelada[0]->pla_tari, -2));
            $gestion = substr($facturaCancelada[0]->pla_tari, 0, -2);


            return json_encode(["success" => true, "message" => "Factura Cancelada", "facturaCancelada" => $facturaCancelada, "literal" => $literal,
                "fechaActual" => $fechaHora, "control" => $control, "orden" => $orden, "leyenda" => utf8_encode($leyenda), "categoria" => $cate, "flim" => $flim, "pagSer" => $pag,
                "con_desc" => $con_desc, "cfc_desc" => $cfc_desc, "tac_desc" => $tac_desc, "mes" => $mesLiteral, "gestion" => $gestion,
                "fechaHoraFactura" => $fechaHoraFactura, "nume" => $nume, "rucnit" => $rucnit, "nombre" => $nombreCompleto, "direccion" => $direccion]);
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function mesLiteral($mes)
    {
        if ($mes === '01')
            $mes = 'ENERO';
        if ($mes === '02')
            $mes = 'FEBRERO';
        if ($mes === '03')
            $mes = 'MARZO';
        if ($mes === '04')
            $mes = 'ABRIL';
        if ($mes === '05')
            $mes = 'MAYO';
        if ($mes === '06')
            $mes = 'JUNIO';
        if ($mes === '07')
            $mes = 'JULIO';
        if ($mes === '08')
            $mes = 'AGOSTO';
        if ($mes === '09')
            $mes = 'SEPTIEMBRE';
        if ($mes === '10')
            $mes = 'OCTUBRE';
        if ($mes === '11')
            $mes = 'NOVIEMBRE';
        if ($mes === '12')
            $mes = 'DICIEMBRE';
        return $mes;
    }

    public function getPlanillaPorPeriodo($matricula, $digito, $periodo)
    {
        $sql = $this->getSqlPlanilla() . " WHERE PLA_MATR = '$matricula' and PLA_DIVE = '$digito' and PLA_TARI = '$periodo'";
        $planilla = $this->executeSelectSQL($sql);
        if (count($planilla) > 0) {
            return $planilla[0];
        }
        return null;
    }

    public function imprimirBase64($matricula, $digito, $facturaCancelada)
    {
        try {
            $fechaHora = date("Y-m-d H:i:s A");

            $monto = $facturaCancelada->pla_vlpg;
            $factura = $facturaCancelada->pla_nfac;

            $literal = NumeroALetras::convert($monto, 'bolivianos', false);

            $sumVlag = $facturaCancelada->pla_form + $facturaCancelada->pla_redon - $facturaCancelada->pla_vlag + $facturaCancelada->pla_vlma + $facturaCancelada->pla_abono;
            $sqlPago = sprintf(
                "select * from pagos WHERE PAG_MATR = '%s' and PAG_DIVE = '%s' and PAG_NFAC = %d",
                $matricula,
                $digito,
                $factura
            );
            $pago = $this->executeSelectSQL($sqlPago);
            $control = $pago[0]->pag_ser;

            //actualizando el campo es_impreso de planilla
            //$this->setEsImpresoPlanilla($matricula, $digito, $factura);

            $pdf = new DOMPDF();
            $pdf->set_paper("Letter");
            $pdf->load_html(utf8_decode($this->html($literal, $facturaCancelada, $fechaHora, $control)));
            $pdf->render();
            $output = $pdf->output();
            return base64_encode($output);
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
            exit;
        }
    }

    private function setEsImpresoPlanilla($matricula, $digito, $factura)
    {
        $sqlUpdateImpreso = sprintf(
            "UPDATE PLANILLA SET ES_IMPRESO = 1 WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and pla_nfac = %d",
            $matricula,
            $digito,
            $factura
        );
        $this->executeSelectSQL($sqlUpdateImpreso);
    }

    private function html($literal, $facturaCancelada, $fechaHora, $control)
    {
        return Factura::getHtml($literal, $facturaCancelada, $fechaHora, $control);
    }

    public function getReporteDiario($fecha_inicial, $fecha_final)
    {

        try {
            if (Permiso::esAdministrador()) {
                //WHERE PLA_INTCRE >= 100 and PLA_FPAG BETWEEN {d'%s'} and {d'%s'} order by PLA_HORA ",
                $sqlPlanilla = sprintf(
                    "select puntcons.USR_MATR, puntcons.USR_DIVE, PLA_NFAC, PLA_VLPG, PLA_TOTMES, PLA_REDON, PLA_NUME, PLA_FPAG, PLA_INTCRE
                            from planilla
                            join puntcons on planilla.pla_matr = puntcons.usr_matr
                            WHERE PLA_INTCRE >= 100 and PLA_FPAG BETWEEN {d'%s'} and {d'%s'} order by PLA_FPAG ",
                    $fecha_inicial,
                    $fecha_final
                );
//                return json_encode(["success" => true, "planilla" => $fecha_inicial, $fecha_final]);
//                exit;
            } else {
                //WHERE PLA_INTCRE = %d and PLA_FPAG BETWEEN {d'%s'} and {d'%s'} order by PLA_HORA"
                $ventanilla = Auth::user()->ventanilla;
                $sqlPlanilla = sprintf(
                    "select USR_MATR, USR_DIVE, PLA_NFAC, PLA_VLPG, PLA_TOTMES, PLA_REDON, PLA_NUME, PLA_FPAG, PLA_INTCRE
                            from planilla
                            join puntcons on planilla.pla_matr = puntcons.usr_matr
                            WHERE PLA_INTCRE = %d and PLA_FPAG BETWEEN {d'%s'} and {d'%s'} order by PLA_FPAG",
                    $ventanilla,
                    $fecha_inicial,
                    $fecha_final
                );
            }
            $planillas = $this->executeSelectSQL($sqlPlanilla);


            $lista = [];
            foreach ($planillas as $row) {
                $matr = $row->usr_matr;
                $puntconsSql = "select usr_matr, CAST(CONVERT(usr_apno USING utf8) AS binary) as nombre from puntcons where usr_matr = '$matr'";
                $puntcons = MySQLController::convertToArray(MySQLController::executeQuery($puntconsSql));
                $ids[] = $puntcons;
                $item = $row;
                array_push($lista, [
                    "pla_fpag" => $row->pla_fpag,
                    "pla_intcre" => $row->pla_intcre,
                    "pla_nfac" => $row->pla_nfac,
                    "pla_nume" => $row->pla_nume,
                    "pla_redon" => $row->pla_redon,
                    "pla_totmes" => $row->pla_totmes,
                    "pla_vlpg" => $row->pla_vlpg,
                    "usr_dive" => $row->usr_dive,
                    "usr_matr" => $row->usr_matr,
                    "nombre" => $puntcons[0]["nombre"]
                ]);
            }

            return json_encode(["success" => true, "planilla" => $lista]);
        } catch (Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    public function getDuplicaCod()
    {
        $cod = [
            ['cod' => '1', 'descripcion' => 'POR DESPERFECTO'],
            ['cod' => '2', 'descripcion' => 'POR FALLA DE IMPRESION'],
            ['cod' => '3', 'descripcion' => 'POR FALTA DE IMPRESION']
        ];
        return json_encode(["success" => true, "codigos" => $cod]);
    }

    public function getAnulaCod()
    {
        $cod = [
            ['cod' => '1', 'descripcion' => 'FALTA DE PAGO'],
            ['cod' => '2', 'descripcion' => 'PAGO PARCIAL'],
            ['cod' => '3', 'descripcion' => 'ERROR DE CODIGO'],
            ['cod' => '4', 'descripcion' => 'ERROR DE CANTIDAD FACTURADA'],
            ['cod' => '5', 'descripcion' => 'ERROR DE INGRESO DATOS(O.I.)'],
            ['cod' => '6', 'descripcion' => 'ERROR DE IMPRESION'],
            ['cod' => '7', 'descripcion' => 'POR RECLAMO PENDIENTE'],
            ['cod' => '8', 'descripcion' => 'ERROR DE REGISTRO'],
            ['cod' => '9', 'descripcion' => 'P.P. NO PAGADO'],
            ['cod' => '10', 'descripcion' => 'A SOLICITUD'],
            ['cod' => '11', 'descripcion' => 'DOBLE INGRESO'],
            ['cod' => '12', 'descripcion' => 'COBRO DE SALDO P.P.']
        ];
        return json_encode(["success" => true, "codigos" => $cod]);
    }

    private function tieneOtroIngreso($matricula, $dive)
    {
        $tieneCorteSql = sprintf(
            "select PUNTCONS.USR_OTRO
                from planilla
                    join puntcons on planilla.pla_matr = puntcons.usr_matr
                WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and PUNTCONS.USR_OTRO > 0",
            $matricula,
            $dive
        );
        return $dataCorte = $this->executeSelectSQL($tieneCorteSql);
    }
}