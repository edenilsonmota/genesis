import * as echarts from 'echarts/core';
import { BarChart } from 'echarts/charts';
import { AriaComponent, GridComponent, TooltipComponent } from 'echarts/components';
import { CanvasRenderer } from 'echarts/renderers';

echarts.use([BarChart, AriaComponent, GridComponent, TooltipComponent, CanvasRenderer]);

const root = document.querySelector('[data-dashboard-overview]');
const element = root?.querySelector('[data-dashboard-members-chart]');
const source = document.querySelector('#dashboard-overview-data');

if (root && element && source) {
    const data = JSON.parse(source.textContent).members_by_church;
    const chart = echarts.init(element);

    if (data.length === 0) {
        chart.setOption({
            graphic: [{ type: 'text', left: 'center', top: 'middle', style: { text: 'Nenhuma igreja ativa cadastrada.', fill: '#526178', font: '14px Instrument Sans' } }],
        });
    } else {
        const maximum = Math.max(...data.map((item) => item.value), 1);
        chart.setOption({
            animationDuration: 650,
            aria: { enabled: true },
            textStyle: { fontFamily: 'Instrument Sans, sans-serif' },
            tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, valueFormatter: (value) => `${value} membros` },
            grid: { left: 12, right: 54, top: 10, bottom: 10, containLabel: true },
            xAxis: { type: 'value', max: maximum, axisLabel: { color: '#526178', precision: 0 }, axisLine: { show: false }, axisTick: { show: false }, splitLine: { lineStyle: { color: '#EEF5FF' } } },
            yAxis: { type: 'category', inverse: true, data: data.map((item) => item.name), axisLabel: { color: '#526178', width: 150, overflow: 'truncate' }, axisLine: { show: false }, axisTick: { show: false } },
            series: [{
                type: 'bar',
                barMaxWidth: 28,
                data: data.map((item) => item.value),
                showBackground: true,
                backgroundStyle: { color: '#EEF5FF', borderRadius: 14 },
                label: { show: true, position: 'right', color: '#0F172A', fontWeight: 600 },
                itemStyle: {
                    color: new echarts.graphic.LinearGradient(0, 0, 1, 0, [{ offset: 0, color: '#0051F5' }, { offset: 1, color: '#2BD9FB' }]),
                    borderRadius: [3, 14, 14, 3],
                    shadowBlur: 10,
                    shadowOffsetX: 3,
                    shadowColor: 'rgba(0,81,245,.18)',
                },
            }],
        });
    }

    new ResizeObserver(() => chart.resize()).observe(element);
}
