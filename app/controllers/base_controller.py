import pyodbc
import asyncpg
from app.models.sql_query import SQLQuery
class BaseController:
    def __init__(self):
        self.dns = 'selaPrueba'
        self.usuario = ''
        self.password = ''
        self.pg_config = {
            "user": "seladeveloper",
            "password": "rootsela",
            "database": "seladb",
            "host": "192.168.1.31",
            "port": 5432
        }

    def get_dbf_connection(self):
        return pyodbc.connect(f'DSN={self.dns};UID={self.usuario};PWD={self.password}')

    async def get_pg_connection(self):
        return await asyncpg.connect(**self.pg_config)

    def transform_value(self, value):
        """Método reutilizable para transformar valores"""
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

    def execute_query(self, sql_query: SQLQuery):
        try:
            conn = self.get_dbf_connection()
            cursor = conn.cursor()
            cursor.execute(sql_query.sql)

            columns = [column[0] for column in cursor.description]
            result = [
                {columns[i]: self.transform_value(row[i]) for i in range(len(columns))}
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