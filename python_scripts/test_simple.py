# test_simple.py
print("بداية الاختبار...")

try:
    import mysql.connector
    print("✓ تم استيراد المكتبة بنجاح")
    
    print("محاولة الاتصال بقاعدة البيانات...")
    conn = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='tailoring_db'
    )
    print("✓ تم الاتصال بنجاح!")
    
    cursor = conn.cursor()
    cursor.execute("SELECT COUNT(*) FROM user")
    count = cursor.fetchone()[0]
    print(f"✓ عدد المستخدمين: {count}")
    
    conn.close()
    print("✓ تم إغلاق الاتصال")
    
except Exception as e:
    print(f"✗ خطأ: {e}")

print("نهاية الاختبار")