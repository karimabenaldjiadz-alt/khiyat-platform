# python_scripts/db_helper.py
import mysql.connector
import sys
import json
import os

# تكوين الاتصال بقاعدة البيانات
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'tailoring_db'
}

def connect_to_db():
    """الاتصال بقاعدة البيانات"""
    try:
        conn = mysql.connector.connect(**db_config)
        return conn
    except mysql.connector.Error as e:
        print(f"خطأ في الاتصال بقاعدة البيانات: {e}")
        return None

def test_connection():
    """اختبار الاتصال بقاعدة البيانات"""
    conn = connect_to_db()
    if conn:
        cursor = conn.cursor()
        cursor.execute("SELECT COUNT(*) FROM user")
        count = cursor.fetchone()[0]
        conn.close()
        print(f"✅ الاتصال ناجح! عدد المستخدمين: {count}")
        return True
    else:
        print("❌ فشل الاتصال بقاعدة البيانات")
        return False

def get_tailors():
    """جلب قائمة الخياطين"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"error": "فشل الاتصال بقاعدة البيانات"})
    
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT t.tailor_id, u.full_name, t.specialization, t.experience_points, t.rating
        FROM tailor t
        JOIN user u ON t.user_id = u.user_id
        ORDER BY t.rating DESC
    """)
    tailors = cursor.fetchall()
    conn.close()
    return json.dumps(tailors, ensure_ascii=False)

def get_tailor_orders(tailor_id):
    """جلب طلبات خياط معين"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"error": "فشل الاتصال بقاعدة البيانات"})
    
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT o.*, u.full_name as customer_name
        FROM `order` o
        JOIN customer c ON o.customer_id = c.customer_id
        JOIN user u ON c.user_id = u.user_id
        WHERE o.tailor_id = %s AND o.status = 'pending'
        ORDER BY o.order_date DESC
    """, (tailor_id,))
    orders = cursor.fetchall()
    conn.close()
    return json.dumps(orders, ensure_ascii=False, default=str)

def create_order(order_data):
    """إنشاء طلب جديد"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"success": False, "message": "فشل الاتصال بقاعدة البيانات"})
    
    try:
        cursor = conn.cursor()
        sql = """
            INSERT INTO `order` 
            (customer_id, tailor_id, description, total_price, delivery_date, design_image, status)
            VALUES (%s, %s, %s, %s, %s, %s, 'pending')
        """
        values = (
            order_data['customer_id'],
            order_data['tailor_id'],
            order_data['description'],
            order_data['total_price'],
            order_data['delivery_date'],
            order_data.get('design_image', '')
        )
        cursor.execute(sql, values)
        conn.commit()
        order_id = cursor.lastrowid
        conn.close()
        return json.dumps({"success": True, "order_id": order_id, "message": "تم إنشاء الطلب بنجاح"})
    except Exception as e:
        conn.rollback()
        conn.close()
        return json.dumps({"success": False, "message": str(e)})

def update_order_status(order_id, status):
    """تحديث حالة الطلب"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"success": False, "message": "فشل الاتصال بقاعدة البيانات"})
    
    try:
        cursor = conn.cursor()
        
        # التحقق من أن الطلب موجود
        cursor.execute("SELECT tailor_id, status FROM `order` WHERE order_id = %s", (order_id,))
        order = cursor.fetchone()
        
        if not order:
            return json.dumps({"success": False, "message": "الطلب غير موجود"})
        
        # تحديث الحالة
        cursor.execute("UPDATE `order` SET status = %s WHERE order_id = %s", (status, order_id))
        
        # إذا كان الطلب مكتملاً، أضف نقاط خبرة
        if status == 'completed':
            cursor.execute("""
                UPDATE tailor 
                SET experience_points = experience_points + 10 
                WHERE tailor_id = %s
            """, (order[0],))
        
        conn.commit()
        conn.close()
        return json.dumps({"success": True, "message": "تم تحديث الحالة بنجاح"})
    except Exception as e:
        conn.rollback()
        conn.close()
        return json.dumps({"success": False, "message": str(e)})

def add_review(review_data):
    """إضافة تقييم جديد"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"success": False, "message": "فشل الاتصال بقاعدة البيانات"})
    
    try:
        cursor = conn.cursor()
        
        # إضافة التقييم
        sql = """
            INSERT INTO review (order_id, customer_id, tailor_id, rating, comment)
            VALUES (%s, %s, %s, %s, %s)
        """
        values = (
            review_data['order_id'],
            review_data['customer_id'],
            review_data['tailor_id'],
            review_data['rating'],
            review_data.get('comment', '')
        )
        cursor.execute(sql, values)
        
        # تحديث متوسط تقييم الخياط
        cursor.execute("""
            UPDATE tailor t
            SET t.rating = (
                SELECT AVG(rating) 
                FROM review 
                WHERE tailor_id = %s
            )
            WHERE t.tailor_id = %s
        """, (review_data['tailor_id'], review_data['tailor_id']))
        
        conn.commit()
        conn.close()
        return json.dumps({"success": True, "message": "تم إضافة التقييم بنجاح"})
    except Exception as e:
        conn.rollback()
        conn.close()
        return json.dumps({"success": False, "message": str(e)})

def get_tailor_stats(tailor_id):
    """جلب إحصائيات الخياط"""
    conn = connect_to_db()
    if not conn:
        return json.dumps({"error": "فشل الاتصال بقاعدة البيانات"})
    
    cursor = conn.cursor(dictionary=True)
    cursor.execute("""
        SELECT 
            t.experience_points,
            t.rating,
            COUNT(DISTINCT o.order_id) as total_orders,
            COUNT(DISTINCT CASE WHEN o.status = 'completed' THEN o.order_id END) as completed_orders,
            COUNT(DISTINCT r.review_id) as total_reviews
        FROM tailor t
        LEFT JOIN `order` o ON t.tailor_id = o.tailor_id
        LEFT JOIN review r ON t.tailor_id = r.tailor_id
        WHERE t.tailor_id = %s
        GROUP BY t.tailor_id
    """, (tailor_id,))
    stats = cursor.fetchone()
    conn.close()
    return json.dumps(stats, ensure_ascii=False, default=str)

# التنفيذ الرئيسي
if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("❌ الرجاء تحديد العملية")
        sys.exit(1)
    
    command = sys.argv[1]
    
    if command == "test":
        test_connection()
    
    elif command == "get_tailors":
        print(get_tailors())
    
    elif command == "get_tailor_orders" and len(sys.argv) > 2:
        tailor_id = int(sys.argv[2])
        print(get_tailor_orders(tailor_id))
    
    elif command == "create_order" and len(sys.argv) > 2:
        # قراءة البيانات من ملف مؤقت
        with open(sys.argv[2], 'r', encoding='utf-8') as f:
            order_data = json.load(f)
        print(create_order(order_data))
    
    elif command == "update_order" and len(sys.argv) > 3:
        order_id = int(sys.argv[2])
        status = sys.argv[3]
        print(update_order_status(order_id, status))
    
    elif command == "add_review" and len(sys.argv) > 2:
        with open(sys.argv[2], 'r', encoding='utf-8') as f:
            review_data = json.load(f)
        print(add_review(review_data))
    
    elif command == "get_tailor_stats" and len(sys.argv) > 2:
        tailor_id = int(sys.argv[2])
        print(get_tailor_stats(tailor_id))
    
    else:
        print("❌ أمر غير صحيح")