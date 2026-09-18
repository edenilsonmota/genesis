const context = document.createElement('canvas').getContext('2d');

const resize = (select) => {
    const option = select.selectedOptions[0];
    if (!option || !context) return;

    const style = window.getComputedStyle(select);
    context.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
    const min = Number(select.dataset.selectMinWidth || 0) * 16;
    const max = Math.min(Number(select.dataset.selectMaxWidth || 28) * 16, window.innerWidth * 0.42);
    const width = Math.max(min, Math.min(max, context.measureText(option.text).width + 52));
    select.style.width = `${width}px`;
    select.title = option.text;
};

document.querySelectorAll('[data-autosize-select]').forEach((select) => {
    resize(select);
    select.addEventListener('change', () => resize(select));
    window.addEventListener('resize', () => resize(select));
});
