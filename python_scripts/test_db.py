# test_db.py
import mysql.connector
import time

print("=" * 50)
print("تشخيص مشكلة الاتصال بقاعدة البيانات")
print("=" * 50)

# 1. معلومات الاتصال
print("\n1. معلومات الاتصال:")
print(f"   Host: localhost")
print(f"   User: root")
print(f"   Password: [فارغة]")
print(f"   Database: tailoring_db")

# 2. محاولة الاتصال مع مهلة زمنية
print("\n2. محاولة الاتصال...")
try:
    # محاولة مع timeout
    start_time = time.time()
    conn = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='tailoring_db',
        connection_timeout=5
    )
    elapsed = time.time() - start_time
    print(f"   ✓ تم الاتصال بعد {elapsed:.2f} ثانية")
    
    # 3. اختبار بسيط
    cursor = conn.cursor()
    cursor.execute("SELECT 1")
    result = cursor.fetchone()
    print(f"   ✓ اختبار الاستعلام: {result}")
    
    # 4. إغلاق الاتصال
    conn.close()
    print("   ✓ تم إغلاق الاتصال")
    
except mysql.connector.Error as e:
    print(f"\n❌ خطأ MySQL: {e}")
    print(f"   Error code: {e.errno}")
    print(f"   SQLSTATE: {e.sqlstate}")
    
except Exception as e:
    print(f"\n❌ خطأ عام: {e}")
    
print("\n" + "=" * 50)
print("انتهى التشخيص")
print("=" * 50)

# 5. اختبار اتصال بدون قاعدة بيانات محددة
print("\n3. اختبار الاتصال بدون تحديد قاعدة بيانات:")
try:
    conn2 = mysql.connector.connect(
        host='localhost',
        user='root',
        password=''
    )
    print("   ✓ تم الاتصال بالخادم")
    
    cursor2 = conn2.cursor()
    cursor2.execute("SHOW DATABASES")
    dbs = cursor2.fetchall()
    print(f"   ✓ قواعد البيانات الموجودة: {[db[0] for db in dbs]}")
    
    conn2.close()
    
except Exception as e:
    print(f"   ❌ {e}")