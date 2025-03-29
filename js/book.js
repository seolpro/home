document.addEventListener("DOMContentLoaded", () => {
    const form = document.getElementById("consult-form");
    const button = form.querySelector("button");
  
    form.addEventListener("submit", async (e) => {
      e.preventDefault();
  
      const name = form.querySelector("[name='name']").value.trim();
      const phone = form.querySelector("[name='phone']").value.trim();
      const email = form.querySelector("[name='email']").value.trim();
      const message = form.querySelector("[name='message']").value.trim();
  
      if (navigator.vibrate) {
        navigator.vibrate(100); // 100ms 동안 진동
      }

      if (!name || !phone || !message) {
        alert("필수 항목을 모두 입력해주세요.");
        return;
      }
  
      if (!confirm(`이대로 상담 요청을 전송할까요?\n\n이름: ${name}\n연락처: ${phone}\n이메일: ${email}\n내용: ${message}`)) {
        return;
      }
  
      // 버튼 상태 변경
      button.disabled = true;
      button.textContent = "🔄 전송중.. 잠시 기다려주세요";
  
      try {
        await fetch("https://script.google.com/macros/s/AKfycbwztOyebKSKLF8wVYSZux5xPgHfxNzHYeCV3MfZvDbylmXVVFEP_ESjMhnIlID1Q3HT/exec", {
          method: "POST",
          mode: "no-cors", // 응답을 못 받는 대신 오류는 피함
          headers: {
            "Content-Type": "application/json",
          },
          body: JSON.stringify({ name, phone, email, message }),
        });
       
        // no-cors는 응답을 못 받으므로 성공 여부를 알 수 없음
        //전송완료시 진동알림.
        if (navigator.vibrate) {
          navigator.vibrate(100); // 100ms 동안 진동
        } 
        // 그냥 성공했다고 가정하고 안내
        alert("🚀상담 요청이 전송되었습니다. 담당자 확인 후 연락드리겠습니다");
        form.reset();
      } catch (error) {
        console.error(error);
        alert("전송 중 오류가 발생했어요. 다시 시도해주세요.");
      } finally {
        button.disabled = false;
        button.textContent = "✔상담요청하기";
      }
    });
  });
  