<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    public function index()
    {
        $vendas = Venda::where('cliente_id', Auth::id())
            ->with('produtos')
            ->latest()
            ->get();

        return view('compras.index', compact('vendas'));
    }

    public function create()
    {
        $produtos = Produto::orderBy('nome')->get();

        return view('compras.create', compact('produtos'));
    }

    public function store(Request $request)
    {
        $itens = collect($request->input('itens', []))
            ->map(fn ($qtd) => (int) $qtd)
            ->filter(fn ($qtd) => $qtd > 0);

        if ($itens->isEmpty()) {
            return back()->withErrors(['itens' => 'Selecione ao menos um item.'])->withInput();
        }

        $produtos = Produto::whereIn('id', $itens->keys())->get()->keyBy('id');

        foreach ($itens as $id => $quantidade) {
            $produto = $produtos->get($id);

            if (! $produto || $quantidade > $produto->estoque) {
                $nome = $produto?->nome ?? "#{$id}";

                return back()
                    ->withErrors(['itens' => "Estoque insuficiente para {$nome}."])
                    ->withInput();
            }
        }

        DB::transaction(function () use ($itens, $produtos) {
            $venda = Venda::create(['cliente_id' => Auth::id()]);

            foreach ($itens as $id => $quantidade) {
                $produto = $produtos[$id];

                $venda->produtos()->attach($produto->id, [
                    'quantidade' => $quantidade,
                    'preco_unitario' => $produto->preco,
                ]);

                $produto->decrement('estoque', $quantidade);
            }
        });

        return redirect()->route('compras.index')->with('sucesso', 'Compra realizada!');
    }
}
