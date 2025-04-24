from fastapi import HTTPException
from app.controllers.base_controller import BaseController
from app.models.sql_query import SQLQuery
from app.models.sql_query import GetConsumoAgua
from app.models.sql_query import PagarConsumoAgua
from datetime import datetime
from datetime import timedelta

class ConsultasController(BaseController):
    def get_count(self):
        try:
            conn = self.get_dbf_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT count(*) FROM puntcons")
            result = cursor.fetchone()
            cursor.close()
            conn.close()
            return {"count": result[0]}
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))

    async def read_consulta(self):
        try:
            conn_pg = await self.get_pg_connection()
            conn_dbf = self.get_dbf_connection()
            cursor = conn_dbf.cursor()

            cursor.execute("SELECT fic_nros,fic_flag FROM ficha where fic_nros like 'N25%' and fic_flag=.T.")
            columns = [column[0] for column in cursor.description]
            result = [
                {columns[i]: str(row[i]).strip() if row[i] is not None else "" for i in range(len(columns))}
                for row in cursor.fetchall()
            ]
            fic_nros = [row["fic_nros"] for row in result]
            placeholders = ', '.join(f"${i+1}" for i in range(len(fic_nros)))
            query = f"SELECT fic_nros,fic_flag FROM hidrometros.ficha WHERE fic_nros IN ({placeholders})"
            fichas = await conn_pg.fetch(query, *fic_nros)
            return fichas
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))
        finally:
            if 'conn_pg' in locals():
                await conn_pg.close()
            if 'conn_dbf' in locals():
                conn_dbf.close()

    def execute_query(self, sql_query: SQLQuery):
        try:
            conn = self.get_dbf_connection()
            cursor = conn.cursor()
            cursor.execute(sql_query.sql)
            if cursor.description is None:
                conn.commit()
                return {"data": "Query executed successfully."}

            columns = [column[0] for column in cursor.description]

            def transform_value(value):
                if value is None:
                    return ""
                value_str = str(value).strip()
                if value_str == "1899-12-30":
                    return ""
                if value_str.lower() == "false":
                    return "0"
                if value_str.lower() == "true":
                    return "1"
                return value_str

            result = [
                {columns[i]: transform_value(row[i]) for i in range(len(columns))}
                for row in cursor.fetchall()
            ]

            return {"data": result}
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))
        finally:
            if 'cursor' in locals():
                cursor.close()
            if 'conn' in locals():
                conn.close()

    def getPlanillaDeuda(self, matricula, dive):
        sql = "SELECT 1 as selected, pla_nume, PLA_MATR, PLA_DIVE, PUNTCONS.USR_RUCNIT, PLA_NFAC as nroFactura, PLA_TARI as periodo," \
                "PLA_TOTACU as total_acumulado, (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as monto, PLA_REDON, PLA_FPAG, PLA_CODI," \
                "(pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as DEUDA_MES," \
                "PLA_CORTE, PUNTCONS.USR_OTRO, PUNTCONS.USR_EPUN, PUNTCONS.USR_CORT, PUNTCONS.usr_apno, PUNTCONS.usr_dirr, puntcons.email, pla_cate, cat_desc," \
                "pla_form as formulario, (pla_vlag + pla_vlma + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon - pla_form) as costoAgua, pla_nmes," \
                "pun_glos, pla_cicl, pun_caja, pla_totmes " \
                "FROM planilla " \
                "JOIN categori ON planilla.pla_cate = categori.usr_cate " \
                "JOIN puntcons ON planilla.pla_matr = puntcons.usr_matr " \
                "JOIN estapunt ON puntcons.usr_epun = estapunt.usr_epun " \
                "WHERE PLA_MATR = '" + matricula + "' AND PLA_DIVE = '" + dive + "' AND empty(PLA_FPAG) AND pla_codi != 8 " \
                "ORDER BY planilla.pla_nfac ASC"
        data = self.execute_query(SQLQuery(sql=sql))
        return data
    def getCategoria20(self, matricula, dive):
        sql = f"""
            SELECT USR_MATR, USR_DIVE, USR_CATE
            FROM puntcons
            WHERE USR_MATR = '{matricula}' AND USR_DIVE = '{dive}' AND usr_cate = 20
        """
        return self.execute_query(SQLQuery(sql=sql))

    def getMultaMorosidad(self, matricula, dive):
        sql = f"""
            SELECT pla_corte as valor, 'MULTAS POR MOROSIDAD' as concepto, '2213' as cuenta, pla_nfac
            FROM planilla
            JOIN puntcons ON planilla.pla_matr = puntcons.usr_matr
            WHERE PLA_MATR = '{matricula}' AND PLA_DIVE = '{dive}' AND empty(PLA_FPAG) AND pla_corte > 0
            ORDER BY planilla.pla_nfac DESC
        """
        return self.execute_query(SQLQuery(sql=sql))

    def getPresupuestoDeuda(self, cuenta):
        sql = f"""
            SELECT TOP 1
                fic_tota as valor,
                cuentas.detalle as concepto,
                ins_cuen as cuenta,
                'tramites' as tipo,
                fic_nros,
                fic_tipo
            FROM ficha
                JOIN tipoinsta ON ficha.fic_tipo = tipoinsta.ins_codi
                JOIN cuentas ON tipoinsta.ins_cuen = cuentas.cuenta
            WHERE fic_tota > 0 AND fic_flag < 1 AND fic_sis = .T. AND fic_matr = '{cuenta}'
                AND (fic_tipo != 7 OR fic_tipo != 8) AND fic_tipo < 24
            ORDER BY fic_nros ASC
        """
        return self.execute_query(SQLQuery(sql=sql))

    def getCorteDeuda(self, cuenta):
        # Implementa esta función según tu necesidad
        return {"data": []}

    def getPuntcons(self, matricula):
        sql = f"""
            SELECT usr_apno, usr_dirr, usr_rucnit, email, cat_desc, usr_numm
            FROM puntcons
            JOIN categori ON puntcons.usr_cate = categori.usr_cate
            WHERE usr_matr = '{matricula}'
        """
        return self.execute_query(SQLQuery(sql=sql))

    def getMontoFormularioOtroIngreso(self):
        sql = """
            SELECT precio as valor, nombre as concepto, cuenta
            FROM cuentas
            WHERE cuenta = 2216
        """
        return self.execute_query(SQLQuery(sql=sql))

    def getMontoFormulario(self):
        sql = """
            SELECT precio
            FROM cuentas
            WHERE cuenta=2216
        """
        result = self.execute_query(SQLQuery(sql=sql))
        if result.get('data'):
            return result['data'][0].get('precio', 0)
        return 0

    def getClientePuntcons(self, categoriaPuntcons, emailPuntcons, nombreCompletoPuntcons, direccionPuntcons, cuenta, rucnitPuntcons):
        return {
            "categoria": categoriaPuntcons,
            "email": emailPuntcons,
            "nombre": nombreCompletoPuntcons,
            "direccion": direccionPuntcons,
            "cuenta": cuenta,
            "rucnit": rucnitPuntcons
        }

    def datosFactura(self, data, montoFormulario):
#         print(f"DEBUG - Datos de la factura: {data.get('nrofactura')}")
        return {
            "selected": data.get('selected', 1),
            "pla_nume": data.get('pla_nume'),
            "pla_matr": data.get('pla_matr'),
            "pla_dive": data.get('pla_dive'),
            "usr_rucnit": data.get('usr_rucnit'),
            "nrofactura": data.get('nrofactura'),
            "periodo": data.get('periodo'),
            "total_acumulado": data.get('total_acumulado'),
            "monto": data.get('monto'),
            "pla_redon": data.get('pla_redon'),
            "pla_fpag": data.get('pla_fpag'),
            "pla_codi": data.get('pla_codi'),
            "deuda_mes": data.get('deuda_mes'),
            "pla_corte": data.get('pla_corte'),
            "usr_otro": data.get('usr_otro'),
            "usr_epun": data.get('usr_epun'),
            "usr_cort": data.get('usr_cort'),
            "usr_apno": data.get('usr_apno'),
            "usr_dirr": data.get('usr_dirr'),
            "email": data.get('email'),
            "pla_cate": data.get('pla_cate'),
            "cat_desc": data.get('cat_desc'),
            "formulario": data.get('formulario'),
            "costoagua": data.get('costoagua'),
            "pla_nmes": data.get('pla_nmes'),
            "pun_glos": data.get('pun_glos'),
            "pla_cicl": data.get('pla_cicl'),
            "pun_caja": data.get('pun_caja')
        }

    def execute_getConsumoAgua(self, getConsumoAgua: GetConsumoAgua):
        try:
            cuenta = getConsumoAgua.cuenta
            matricula = cuenta[0:6]
            digito = cuenta[-1]

            # Obtener datos principales
            data = self.getPlanillaDeuda(matricula, digito)
#             print(f"DEBUG - Resultados obtenidos: {data.get('data')} ")

            # Si no hay datos, retornar mensaje
            if not data.get('data'):
                return {"success": False, "message": "Este número de cuenta no tiene deudas pendientes. Revise el número de cuenta e intente nuevamente"}

            # Obtener datos adicionales
            categoria20 = self.getCategoria20(matricula, digito)
            dataTieneMultaMorosidad = self.getMultaMorosidad(matricula, digito)
            dataPresupuesto = self.getPresupuestoDeuda(cuenta)

            # Procesar corte de deuda
            dataCorte = {"valor": 0}
            if not categoria20.get('data'):
                corte_result = self.getCorteDeuda(cuenta)
                if corte_result.get('data'):
                    dataCorte['valor'] = corte_result['data'][0].get('valor', 0)

            # Procesar otros ingresos
            otroIngre = []
            factus = []

            # Verificar si hay multas, cortes o presupuestos
            if (dataTieneMultaMorosidad.get('data') or
                (dataCorte['valor'] > 0 and not categoria20.get('data')) or
                dataPresupuesto.get('data')):

                montoFormulario = self.getMontoFormularioOtroIngreso()
                if montoFormulario.get('data'):
                    dataPresupuesto['data'].append(montoFormulario['data'][0])

            # Obtener datos de puntcons
            puntcons = self.getPuntcons(matricula)

            # Agregar multas morosidad si existen
            if dataTieneMultaMorosidad.get('data'):
                dataPresupuesto['data'].append(dataTieneMultaMorosidad['data'][0])

            # Agregar corte de deuda si aplica
            if dataCorte['valor'] > 0 and not categoria20.get('data'):
                dataPresupuesto['data'].append({
                    "valor": dataCorte['valor'],
                    "concepto": "CORTE DE DEUDA",
                    "cuenta": "2213"
                })

            # Asignar otros ingresos si hay datos
            if dataPresupuesto.get('data'):
                otroIngre = dataPresupuesto['data']

            # Procesar datos principales
            if data['data'][0].get('pun_caja') == 0:
                return {"success": False, "message": data['data'][0].get('pun_glos', '')}

            # Obtener datos del cliente
            cliente_data = puntcons['data'][0] if puntcons.get('data') else {}
            cliente = self.getClientePuntcons(
                categoriaPuntcons=cliente_data.get('cat_desc', ''),
                emailPuntcons=cliente_data.get('email', ''),
                nombreCompletoPuntcons=cliente_data.get('usr_apno', ''),
                direccionPuntcons=cliente_data.get('usr_dirr', ''),
                cuenta=cuenta,
                rucnitPuntcons=cliente_data.get('usr_rucnit', '')
            )

            # Obtener monto de formulario
            montoFormulario = self.getMontoFormulario()

            # Procesar facturas
            for fac in data['data']:
                factus.append(self.datosFactura(fac, montoFormulario))

            # Construir respuesta final
            factura = {
                "cliente": cliente,
                "facturas": factus,
                "otrosIngresos": otroIngre
            }

            return {"success": True, "factura": factura}

        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))
        finally:
            if 'cursor' in locals():
                cursor.close()
            if 'conn' in locals():
                conn.close()
    def execute_pagarConsumo(self, pagarConsumoAgua: PagarConsumoAgua):
        try:
#                         $fecha_pago = date('Y-m-d');
#
#                         $fechaFin = date("Y-m-d H:i:s", strtotime($fecha_pago ));
#                         $fechaFin1 = strtotime('+23 hour', strtotime($fechaFin));
#                         $fechaFin2 = date("Y-m-d H:i:s", $fechaFin1);
#
#                         $fechaInicio = date("Y-m-d H:i:s", strtotime($fecha_pago));

#             $fechaHora = date("Y-m-d H:i:s A");
#             $fechaPago = date("Y-m-d");
#             $matricula = substr($cuenta, 0, -1);
#             $digito = substr($cuenta, -1);
#             $facturasComa = implode(",", $nfacs);

            fecha_pago = datetime.now().strftime("%Y-%m-%d")
            fechaFin = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
            fechaFin1 = datetime.strptime(fechaFin, "%Y-%m-%d %H:%M:%S") + timedelta(hours=23)
            fechaFin2 = fechaFin1.strftime("%Y-%m-%d %H:%M:%S")
            fechaInicio = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

#             cuenta = pagarConsumoAgua.cuenta
            return { "success": 1, "message": "" }
        except Exception as e:
            raise HTTPException(status_code=500, detail=str(e))
        finally:
            if 'cursor' in locals():
               cursor.close()
            if 'conn' in locals():
               conn.close()
# Instancias para exportar
controller = ConsultasController()
get_count = controller.get_count
read_consulta = controller.read_consulta
execute_query = controller.execute_query
execute_getConsumoAgua = controller.execute_getConsumoAgua
execute_pagarConsumo = controller.execute_pagarConsumo