// api.js — دوال مساعدة للاتصال بالباك إند (PHP)
// غيّري المسار ده لو الـ API في مكان مختلف
const API_BASE = "api";

async function apiPost(endpoint, data) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    credentials: "include",          // مهم: عشان الجلسة (session cookie) تتبعت
    body: JSON.stringify(data || {})
  });
  return res.json();
}

async function apiGet(endpoint) {
  const res = await fetch(`${API_BASE}/${endpoint}`, {
    method: "GET",
    credentials: "include"
  });
  return res.json();
}
