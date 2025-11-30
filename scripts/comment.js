    const dialog = document.querySelector('dialog');
    const originalText = dialog.innerHTML.trim(); // 获取原始文本 "编辑评论内容"
    
    // 解析属性
    const types = dialog.dataset.type.split(',');
    const inputPlaceholder = dialog.dataset.ed;
    const [methodStr, btnClass, btnText] = dialog.dataset.eb.split(',');
    const methodName = methodStr.replace('()', ''); // 提取方法名 "sm"

    // 清空dialog原有内容
    dialog.innerHTML = '';

    // 创建输入框
    const input = document.createElement('textarea');
    input.id='dt';
    input.placeholder = inputPlaceholder;
    dialog.appendChild(input);

    const header = document.createElement('div');
    header.id='dh';
    header.innerHTML='<img src="../imgs/return.png" height="80%" onclick="document.querySelector(\'dialog\').close();"/><h2 class="mid">'+inputPlaceholder+'</h2>';
    dialog.appendChild(header);

    // 创建提交按钮
    const submitBtn = document.createElement('button');
    submitBtn.className = btnClass;
    submitBtn.textContent = btnText;
    header.appendChild(submitBtn);

    // 提交按钮点击事件
    submitBtn.addEventListener('click', () => {
        if (typeof window[methodName] === 'function') {
            window[methodName](input.value); // 调用sm函数并传入输入值
        }
        dialog.close();
    });

    // 点击外部关闭对话框
    dialog.addEventListener('click', (e) => {
        const rect = dialog.getBoundingClientRect();
        if (
            e.clientX < rect.left ||
            e.clientX > rect.right ||
            e.clientY < rect.top ||
            e.clientY > rect.bottom
        ) {
            dialog.close();
        }
    });

    // 点击触发按钮打开对话框
    document.getElementById('start').addEventListener('click', () => {
        dialog.showModal();
        input.focus(); // 自动聚焦输入框
    });

/* 示例sm函数 */
function sm(value) {
    //向服务器通信
    document.querySelector('dialog').close();
}
