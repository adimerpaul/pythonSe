from fastapi import HTTPException
from app.controllers.base_controller import BaseController
from app.models.sql_query import SQLQuery
from app.models.sql_query import GetConsumoAgua

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
#         public function getPlanillaDeuda($matricula, $dive)
#         {
#             $sql = sprintf(
#                 "select 1 as selected, pla_nume , PLA_MATR, PLA_DIVE, PUNTCONS.USR_RUCNIT, PLA_NFAC as nroFactura, PLA_TARI as periodo,
#            PLA_TOTACU as total_acumulado, (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as monto, PLA_REDON, PLA_FPAG, PLA_CODI,
#            (pla_vlag + pla_vlma + pla_form + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon) as DEUDA_MES,
#            PLA_CORTE, PUNTCONS.USR_OTRO, PUNTCONS.USR_EPUN, PUNTCONS.USR_CORT, PUNTCONS.usr_apno, PUNTCONS.usr_dirr, puntcons.email, pla_cate, cat_desc,
#            pla_form as formulario, (pla_vlag + pla_vlma + pla_leyprv + pla_abono + pla_inag + pla_cuocre + pla_redon - pla_form) as costoAgua, pla_nmes,
#            pun_glos, pla_cicl, pun_caja, pla_totmes
#                     from planilla
#                         join categori on planilla.pla_cate = categori.usr_cate
#                         join puntcons on planilla.pla_matr = puntcons.usr_matr
#                         join estapunt on puntcons.usr_epun = estapunt.usr_epun
#                     WHERE PLA_MATR = '%s' and PLA_DIVE = '%s' and empty(PLA_FPAG) and pla_codi != 8
#                     order by planilla.pla_nfac asc",
#                 $matricula,
#                 $dive
#             );
#
#             $data = $this->executeSelectSQL($sql);
#             return $data;
#         }
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

    def execute_getConsumoAgua(self, getConsumoAgua: GetConsumoAgua):
        try:
            cuenta = getConsumoAgua.cuenta
            matricula = cuenta[0:6]
            digito = cuenta[-1]
            data = self.getPlanillaDeuda(matricula, digito)
            return data
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