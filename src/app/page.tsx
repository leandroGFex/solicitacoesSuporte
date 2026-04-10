import DashboardLayout from '@/components/dashboard-layout'
import { 
  TrendingUp, 
  Clock, 
  CheckCircle2, 
  AlertCircle 
} from 'lucide-react'

const stats = [
  { label: 'Total de Solicitações', value: '1,284', change: '+12%', icon: TrendingUp, color: 'text-blue-500' },
  { label: 'Em Andamento', value: '42', change: '-5', icon: Clock, color: 'text-yellow-500' },
  { label: 'Concluídas (Hoje)', value: '18', change: '+2', icon: CheckCircle2, color: 'text-green-500' },
  { label: 'Pendências / Atrasos', value: '03', change: '0', icon: AlertCircle, color: 'text-red-500' },
]

export default function Home() {
  return (
    <DashboardLayout>
      <div className="space-y-8">
        <div className="flex items-center justify-between">
          <div className="space-y-1">
            <h2 className="text-3xl font-bold tracking-tight text-white">Olá, Admin!</h2>
            <p className="text-muted-foreground font-medium">
              Bem-vindo ao centro operacional do Grupo Flex.
            </p>
          </div>
          <button className="bg-blue-600 hover:bg-blue-500 text-white font-semibold py-2.5 px-6 rounded-xl transition-all shadow-lg shadow-blue-500/20 active:scale-95 cursor-pointer">
            Nova Solicitação
          </button>
        </div>

        {/* Estatísticas Rápidas */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          {stats.map((stat, i) => (
            <div key={i} className="glass-dark p-6 rounded-2xl group hover:border-blue-500/30 transition-all cursor-default">
              <div className="flex items-center justify-between mb-4">
                <div className={`p-2 rounded-xl bg-white/5 border border-white/10 group-hover:bg-blue-500/10 transition-colors ${stat.color}`}>
                  <stat.icon size={24} />
                </div>
                <span className={`text-xs font-bold px-2 py-1 rounded-lg bg-white/5 border border-white/10 ${stat.change.startsWith('+') ? 'text-green-500' : 'text-yellow-500'}`}>
                  {stat.change}
                </span>
              </div>
              <p className="text-gray-400 text-sm font-medium">{stat.label}</p>
              <p className="text-3xl font-bold text-white mt-1 group-hover:text-blue-400 transition-colors">{stat.value}</p>
            </div>
          ))}
        </div>

        {/* Dashboards Visuais */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          <div className="lg:col-span-2 glass-dark p-8 rounded-3xl min-h-[400px]">
            <div className="flex items-center justify-between mb-8">
              <h3 className="text-xl font-bold text-white">Relatório de Desempenho</h3>
              <select className="bg-white/5 border border-white/10 rounded-lg py-1 px-3 text-sm text-gray-400 focus:outline-hidden cursor-pointer">
                <option>Últimos 7 dias</option>
                <option>Últimos 30 dias</option>
              </select>
            </div>
            <div className="h-64 flex items-center justify-center text-gray-600 italic">
              Dashboard de Gráficos (Aguardando Integração Recharts)
            </div>
          </div>

          <div className="glass-dark p-8 rounded-3xl space-y-6">
            <h3 className="text-xl font-bold text-white">Atividades Recentes</h3>
            <div className="space-y-6">
              {[1, 2, 3, 4].map(i => (
                <div key={i} className="flex gap-4 group">
                  <div className="h-10 w-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center flex-shrink-0 group-hover:bg-blue-500/20 transition-colors">
                    <Clock size={18} className="text-gray-400 group-hover:text-blue-400 transition-colors" />
                  </div>
                  <div>
                    <p className="text-sm font-semibold text-white group-hover:text-blue-400 transition-colors">
                      Card #3842 movido para 'Enviado'
                    </p>
                    <p className="text-xs text-gray-500 mt-0.5">há 15 minutos • Sistema de Automação</p>
                  </div>
                </div>
              ))}
            </div>
            <button className="w-full py-3 text-sm font-medium text-gray-400 hover:text-white bg-white/5 hover:bg-white/10 rounded-xl transition-all h-full">
              Ver Histórico Completo
            </button>
          </div>
        </div>
      </div>
    </DashboardLayout>
  )
}
